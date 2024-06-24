<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $data = (array)$this->getRequest('data');
        $resellerId = isset($data['resellerId']) ? (int)$data['resellerId'] : 0;
        $notificationType = isset($data['notificationType']) ? (int)$data['notificationType'] : 0;

        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => false,
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        if ($resellerId === 0) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }

        if ($notificationType === 0) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($resellerId);
        if ($reseller === null) {
            throw new \Exception('Seller not found!', 404);
        }

        $clientId = isset($data['clientId']) ? (int)$data['clientId'] : 0;
        $client = Contractor::getById($clientId);
        if ($client === null || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $resellerId) {
            throw new \Exception('Client not found!', 404);
        }

        $cFullName = $client->getFullName() ?: $client->name;

        $creatorId = isset($data['creatorId']) ? (int)$data['creatorId'] : 0;
        $creator = Employee::getById($creatorId);
        if ($creator === null) {
            throw new \Exception('Creator not found!', 404);
        }

        $expertId = isset($data['expertId']) ? (int)$data['expertId'] : 0;
        $expert = Employee::getById($expertId);
        if ($expert === null) {
            throw new \Exception('Expert not found!', 404);
        }

        $differences = '';
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && !empty($data['differences'])) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName((int)$data['differences']['from']),
                'TO' => Status::getName((int)$data['differences']['to']),
            ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID' => isset($data['complaintId']) ? (int)$data['complaintId'] : 0,
            'COMPLAINT_NUMBER' => $data['complaintNumber'] ?? '',
            'CREATOR_ID' => $creatorId,
            'CREATOR_NAME' => $creator->getFullName(),
            'EXPERT_ID' => $expertId,
            'EXPERT_NAME' => $expert->getFullName(),
            'CLIENT_ID' => $clientId,
            'CLIENT_NAME' => $cFullName,
            'CONSUMPTION_ID' => isset($data['consumptionId']) ? (int)$data['consumptionId'] : 0,
            'CONSUMPTION_NUMBER' => $data['consumptionNumber'] ?? '',
            'AGREEMENT_NUMBER' => $data['agreementNumber'] ?? '',
            'DATE' => $data['date'] ?? '',
            'DIFFERENCES' => $differences,
        ];

        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                throw new \Exception("Template Data ({$key}) is empty!", 500);
            }
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if (!empty($emailFrom) && count($emails) > 0) {
            foreach ($emails as $email) {
                MessagesClient::sendMessage([
                    0 => [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $email,
                        'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
                $result['notificationEmployeeByEmail'] = true;
            }
        }

        if ($notificationType === self::TYPE_CHANGE && !empty($data['differences']['to'])) {
            if (!empty($emailFrom) && !empty($client->email)) {
                MessagesClient::sendMessage([
                    0 => [
                        'emailFrom' => $emailFrom,
                        'emailTo' => $client->email,
                        'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                        'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                    ],
                ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to']);
                $result['notificationClientByEmail'] = true;
            }

            if (!empty($client->mobile)) {
                $res = NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, (int)$data['differences']['to'], $templateData, $error);
                if ($res) {
                    $result['notificationClientBySms']['isSent'] = true;
                }
                if (!empty($error)) {
                    $result['notificationClientBySms']['message'] = $error;
                }
            }
        }

        return $result;
    }
}
