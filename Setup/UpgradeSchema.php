<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{

    const WORLDPAY_NOTIFICATION_HISTORY = 'worldpay_notification_history';
    const WORLDPAY_PAYMENT = 'worldpay_payment';
    const AWP_OMS_PARAMS = 'awp_oms_params';
    const AWP_OMS_PARTIAL_SETTLEMENTS = 'awp_oms_partial_settlements';
    const ACCESSWORLDPAY_VERIFIEDTOKEN = 'accessworldpay_verifiedtoken';

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        if (version_compare($context->getVersion(), '1.1.0', '<')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable(self::WORLDPAY_NOTIFICATION_HISTORY)
            )
            ->addColumn(
                'id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                [
                        'identity' => true,
                        'nullable' => false,
                        'primary'  => true,
                        'unsigned' => true,
                    ],
                'Id'
            )
            ->addColumn(
                'order_id',
                \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
                null,
                ['nullable' => false,
                    'unsigned' => true],
                'Order Id'
            )
            ->addColumn(
                'status',
                \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                255,
                [],
                'Status'
            )
            ->addColumn(
                'created_at',
                \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
                null,
                ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
                'Created At'
            )
            ->addIndex(
                $installer->getIdxName(self::WORLDPAY_NOTIFICATION_HISTORY, ['order_id']),
                ['order_id']
            )
            ->setComment('AccessWorldpay Notification History')
            ->setOption('type', 'InnoDB')
            ->setOption('charset', 'utf8');
                        $installer->getConnection()->createTable($table);
        }

        $setup->getConnection()->changeColumn(
            $setup->getTable(self::WORLDPAY_NOTIFICATION_HISTORY),
            'order_id',
            'order_id',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'length' => 255,
                'comment' => 'AccessWorldpay order id'
            ]
        );
        if (version_compare($context->getVersion(), '1.2.0', '<')) {
            $this->addColumnWP($installer);
        }
        if (version_compare($context->getVersion(), '1.2.1', '<')) {
            $this->createOmsParametersTable($installer);
        }
        if (version_compare($context->getVersion(), '1.2.2', '<')) {
            $this->createPartialSettlementsTable($installer);
        }
        /* Add Disclaimer */
        if (version_compare($context->getVersion(), '1.2.2', '<')) {
            $this->addColumnDisclaimer($installer);
        }
        /* Add Card Brand */
        if (version_compare($context->getVersion(), '1.2.3', '<')) {
            $this->addColumnCardBrand($installer);
        }
        /* Add CardOnFileAuthLink */
        if (version_compare($context->getVersion(), '1.2.4', '<')) {
            $this->addColumnCardOnFileAuthLink($installer);
        }
        $installer->endSetup();
    }

    /**
     * @param SchemaSetupInterface $installer
     * @return void
     */
    private function addColumnWP(SchemaSetupInterface $installer)
    {
        $connection = $installer->getConnection();
        $connection->addColumn(
            $installer->getTable(self::WORLDPAY_PAYMENT),
            'aav_address_result_code',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '25',
                'comment' => 'AAV Address Result Code',
                'after' => 'risk_provider_final'
            ]
        );

        $connection->addColumn(
            $installer->getTable(self::WORLDPAY_PAYMENT),
            'avv_postcode_result_code',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '25',
                'comment' => 'AAV Postcode Result Code',
                'after' => 'aav_address_result_code'
            ]
        );

        $connection->addColumn(
            $installer->getTable(self::WORLDPAY_PAYMENT),
            'aav_cardholder_name_result_code',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '25',
                'comment' => 'AAV Cardholder Name Result Code',
                'after' => 'avv_postcode_result_code'
            ]
        );

        $connection->addColumn(
            $installer->getTable(self::WORLDPAY_PAYMENT),
            'aav_telephone_result_code',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '25',
                'comment' => 'AAV Telephone Result Code',
                'after' => 'aav_cardholder_name_result_code'
            ]
        );
        $connection->addColumn(
            $installer->getTable(self::WORLDPAY_PAYMENT),
            'aav_email_result_code',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '25',
                'comment' => 'AAV Email Result Code',
                'after' => 'aav_telephone_result_code'
            ]
        );

        $connection->addColumn(
            $installer->getTable(self::WORLDPAY_PAYMENT),
            'interaction_type',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => true,
                'length' => '25',
                'comment' => 'Interaction Type',
                'after' => 'aav_email_result_code'
            ]
        );
    }

    /**
     * Create OMS Parameters table
     *
     * @param SchemaSetupInterface $setup
     * @return $this
     */
    private function createOmsParametersTable(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        /**
         * Create table 'awp_oms_params'
         */
        $table = $installer->getConnection()->newTable(
            $installer->getTable(self::AWP_OMS_PARAMS)
        )->addColumn(
            'entity_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            10,
            ['identity' => true, 'nullable' => false, 'unsigned' => true, 'primary' => true],
            'Entity ID'
        )->addColumn(
            'order_increment_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            40,
            [],
            'Order Id'
        )->addColumn(
            'awp_order_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '40',
            [],
            'AWP Order Code'
        )->addColumn(
            'awp_payment_status',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            [],
            'Payment Status'
        )->addColumn(
            'awp_cancel_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Payment Cancel Parameter'
        )->addColumn(
            'awp_settle_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Payment Settle Parameter'
        )->addColumn(
            'awp_partial_settle_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Payment Partial Settle Parameter'
        )->addColumn(
            'awp_events_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Payment Events Parameter'
        )->addColumn(
            'awp_refund_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Payment Refund Parameter'
        )->addColumn(
            'awp_partial_refund_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Payment Partial Refund Parameter'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
            'Created At'
        )->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Updated At'
        )->setComment(
            'AccessWorldpay OMS Params'
        );
        $installer->getConnection()->createTable($table);

        return $this;
    }
    
    /**
     * Create OMS Parameters table
     *
     * @param SchemaSetupInterface $setup
     * @return $this
     */
    private function createPartialSettlementsTable(SchemaSetupInterface $setup)
    {
        $installer = $setup;

        /**
         * Create table 'awp_oms_partial_settlements'
         */
        $table = $installer->getConnection()->newTable(
            $installer->getTable(self::AWP_OMS_PARTIAL_SETTLEMENTS)
        )->addColumn(
            'entity_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            10,
            ['identity' => true, 'nullable' => false, 'unsigned' => true, 'primary' => true],
            'Entity ID'
        )->addColumn(
            'order_increment_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            40,
            [],
            'Order Id'
        )->addColumn(
            'order_invoice_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            40,
            [],
            'Order Invoice Id'
        )->addColumn(
            'order_item_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            40,
            [],
            'Order Item Id'
        )->addColumn(
            'awp_order_code',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            '40',
            [],
            'AWP Order Code'
        )->addColumn(
            'awp_lineitem_cancel_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Line Item Payment Cancel Parameter'
        )->addColumn(
            'awp_lineitem_refund_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Line Item Refund Parameter'
        )->addColumn(
            'awp_lineitem_partial_refund_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Line Item partial Refund Parameter'
        )->addColumn(
            'awp_lineitem_partial_settle_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Line Item Partial Settle Parameter'
        )->addColumn(
            'awp_lineitem_events_param',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            3000,
            [],
            'Line Item Payment Events Parameter'
        )->addColumn(
            'created_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT],
            'Created At'
        )->addColumn(
            'updated_at',
            \Magento\Framework\DB\Ddl\Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => \Magento\Framework\DB\Ddl\Table::TIMESTAMP_INIT_UPDATE],
            'Updated At'
        )->setComment(
            'AccessWorldpay OMS Partial Settlements'
        );
        $installer->getConnection()->createTable($table);

        return $this;
    }

    /**
     * Add Disclaimer Flag
     * @param SchemaSetupInterface $installer
     * @return void
     */
    private function addColumnDisclaimer(SchemaSetupInterface $installer)
    {
        $connection = $installer->getConnection();
        $connection->addColumn(
            $installer->getTable(self::ACCESSWORLDPAY_VERIFIEDTOKEN),
            'disclaimer_flag',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_BOOLEAN,
                'nullable' => false,
                'comment' => 'Disclaimer Flag',
                'before' => 'created_at'
            ]
        );
    }
    
    /**
     * Add CardBrand
     * @param SchemaSetupInterface $installer
     * @return void
     */
    private function addColumnCardBrand(SchemaSetupInterface $installer)
    {
        $connection = $installer->getConnection();
        $connection->addColumn(
            $installer->getTable(self::ACCESSWORLDPAY_VERIFIEDTOKEN),
            'card_brand',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => false,
                'comment' => 'card_brand',
                'after' => 'created_at'
            ]
        );
    }
    
    /**
     * Add CardOnFileAuthLink
     * @param SchemaSetupInterface $installer
     * @return void
     */
    private function addColumnCardOnFileAuthLink(SchemaSetupInterface $installer)
    {
        $connection = $installer->getConnection();
        $connection->addColumn(
            $installer->getTable(self::ACCESSWORLDPAY_VERIFIEDTOKEN),
            'cardonfile_auth_link',
            [
                'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                'nullable' => false,
                'comment' => 'cardonfile_auth_link',
                'after' => 'created_at'
            ]
        );
    }
}
