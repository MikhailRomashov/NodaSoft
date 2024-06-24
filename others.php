<?php

namespace NW\WebService\References\Operations\Notification;

/**
 * @property Seller $Seller
 */
class Contractor
{
    const TYPE_CUSTOMER = 0;
    public $id;
    public $type;
    public $name;

    public function __construct(int $id, int $type = self::TYPE_CUSTOMER, string $name = "")
    {
        $this->id = $id;
        $this->type = $type;
        $this->name = $name;
    }

    public static function getById(int $resellerId): self
    {
        // In real scenario, fetch data from database and create an instance
        return new self($resellerId);
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}

class Seller extends Contractor
{
}

class Employee extends Contractor
{
}

class Status
{
    public $id;
    public $name;

    public static function getName(int $id): string
    {
        $statuses = [
            0 => 'Completed',
            1 => 'Pending',
            2 => 'Rejected',
        ];

        return $statuses[$id] ?? 'Unknown status';
    }
}

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest($pName)
    {
        // Ensure the parameter is safe
        return htmlspecialchars($_REQUEST[$pName] ?? '');
    }
}

function getResellerEmailFrom()
{
    return 'contractor@example.com';
}

function getEmailsByPermit($resellerId, $event)
{
    // In real scenario, fetch emails from database based on resellerId and event
    return ['someemail@example.com', 'someemail2@example.com'];
}

class NotificationEvents
{
    const CHANGE_RETURN_STATUS = 'changeReturnStatus';
    const NEW_RETURN_STATUS    = 'newReturnStatus';
}
