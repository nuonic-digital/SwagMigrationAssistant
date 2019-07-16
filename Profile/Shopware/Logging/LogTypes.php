<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\Logging;

final class LogTypes
{
    public const ASSOCIATION_REQUIRED_MISSING = 'SWAG_MIGRATION__SHOPWARE_ASSOCIATION_REQUIRED_MISSING';
    public const CANNOT_DOWNLOAD_MEDIA = 'SWAG_MIGRATION__SHOPWARE_CANNOT_DOWNLOAD_DATA';
    public const CANNOT_DOWNLOAD_ORDER_DOCUMENT = 'SWAG_MIGRATION__SHOPWARE_CANNOT_DOWNLOAD_ORDER_DOCUMENT';
    public const CANNOT_COPY_MEDIA = 'SWAG_MIGRATION__SHOPWARE_CANNOT_COPY_DATA';
    public const SOURCE_FILE_NOT_FOUND = 'SWAG_MIGRATION__SHOPWARE_SOURCE_FILE_NOT_FOUND';
    public const EMPTY_LOCALE = 'SWAG_MIGRATION__SHOPWARE_EMPTY_LOCALE';
    public const EMPTY_LINE_ITEM_IDENTIFIER = 'SWAG_MIGRATION__SHOPWARE_EMPTY_LINE_ITEM_IDENTIFIER';
    public const EMPTY_NECESSARY_DATA_FIELDS = 'SWAG_MIGRATION__SHOPWARE_EMPTY_NECESSARY_DATA_FIELDS';
    public const INVALID_UNSERIALIZED_DATA = 'SWAG_MIGRATION__SHOPWARE_INVALID_UNSERIALIZED_DATA';
    public const NO_ADDRESS_DATA = 'SWAG_MIGRATION__SHOPWARE_NO_ADDRESS_DATA';
    public const NO_DEFAULT_BILLING_AND_SHIPPING_ADDRESS = 'SWAG_MIGRATION__SHOPWARE_NO_DEFAULT_BILLING_AND_SHIPPING_ADDRESS';
    public const NO_DEFAULT_BILLING_ADDRESS = 'SWAG_MIGRATION__SHOPWARE_NO_DEFAULT_BILLING_ADDRESS';
    public const NO_DEFAULT_SHIPPING_ADDRESS = 'SWAG_MIGRATION__SHOPWARE_NO_DEFAULT_SHIPPING_ADDRESS';
    public const NOT_CONVERTABLE_OBJECT_TYPE = 'SWAG_MIGRATION__SHOPWARE_NOT_CONVERT_ABLE_OBJECT_TYPE';
    public const PRODUCT_MEDIA_NOT_CONVERTED = 'SWAG_MIGRATION__SHOPWARE_PRODUCT_MEDIA_NOT_CONVERTED';
    public const PROPERTY_MEDIA_NOT_CONVERTED = 'SWAG_MIGRATION__SHOPWARE_PROPERTY_MEDIA_NOT_CONVERTED';
    public const UNKNOWN_ORDER_STATE = 'SWAG_MIGRATION__SHOPWARE_UNKNOWN_ORDER_STATE';
    public const UNKNOWN_PAYMENT_METHOD = 'SWAG_MIGRATION__SHOPWARE_UNKNOWN_PAYMENT_METHOD';
    public const UNKNOWN_TRANSACTION_STATE = 'SWAG_MIGRATION__SHOPWARE_UNKNOWN_TRANSACTION_STATE';
    public const UNKNOWN_CUSTOMER_SALUTATION = 'SWAG_MIGRATION__SHOPWARE_UNKNOWN_CUSTOMER_SALUTATION';
}