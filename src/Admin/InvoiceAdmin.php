<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\Invoice;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;

class InvoiceAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
        $collection->remove('edit');
        $collection->remove('delete');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('invoiceNumber', null, [
                'label' => 'list.label_invoice_number',
            ])
            ->add('status', null, [
                'label' => 'list.label_invoice_status',
                'template' => 'admin/invoice/list_status.html.twig',
            ])
            ->add('issueDate', null, [
                'label' => 'list.label_invoice_issue_date',
                'format' => 'Y-m-d',
            ])
            ->add('relatedOrder', null, [
                'label' => 'list.label_order',
            ])
            ->add('createdAt', 'datetime', [
                'label' => 'list.label_created_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('invoiceNumber', null, [
                'label' => 'show.label_invoice_number',
            ])
            ->add('status', null, [
                'label' => 'show.label_invoice_status',
                'template' => 'admin/invoice/show_status.html.twig',
            ])
            ->add('issueDate', null, [
                'label' => 'show.label_invoice_issue_date',
            ])
            ->add('dueDate', null, [
                'label' => 'show.label_invoice_due_date',
            ])
            ->add('currency', null, [
                'label' => 'show.label_currency',
            ])
            ->add('amountFreight', null, [
                'label' => 'show.label_invoice_amount_freight',
            ])
            ->add('amountCommission', null, [
                'label' => 'show.label_invoice_amount_commission',
            ])
            ->add('amountSubtotal', null, [
                'label' => 'show.label_invoice_amount_subtotal',
            ])
            ->add('amountVat', null, [
                'label' => 'show.label_invoice_amount_vat',
            ])
            ->add('amountGross', null, [
                'label' => 'show.label_invoice_amount_gross',
            ])
            ->add('vatRatePercent', null, [
                'label' => 'show.label_vat_rate_percent',
            ])
            ->add('feePercent', null, [
                'label' => 'show.label_platform_fee_percent',
            ])
            ->add('sellerName', null, [
                'label' => 'show.label_invoice_seller_name',
            ])
            ->add('sellerRegistrationNumber', null, [
                'label' => 'show.label_registration_number',
            ])
            ->add('sellerVatNumber', null, [
                'label' => 'show.label_vat_number',
            ])
            ->add('sellerAddressLine1', null, [
                'label' => 'show.label_invoice_seller_address',
            ])
            ->add('sellerAddressLine2', null, [
                'label' => 'show.label_invoice_seller_address_2',
            ])
            ->add('sellerEmail', null, [
                'label' => 'show.label_email',
            ])
            ->add('sellerPhone', null, [
                'label' => 'show.label_phone',
            ])
            ->add('buyerCompanyName', null, [
                'label' => 'show.label_company_name',
            ])
            ->add('buyerRegistrationNumber', null, [
                'label' => 'show.label_company_registration_number',
            ])
            ->add('buyerVatNumber', null, [
                'label' => 'show.label_vat_number',
            ])
            ->add('buyerAddress', null, [
                'label' => 'show.label_company_address',
            ])
            ->add('buyerEmailSnapshot', null, [
                'label' => 'show.label_invoice_buyer_email',
            ])
            ->add('orderReference', null, [
                'label' => 'show.label_order_number',
            ])
            ->add('pickupAddress', null, [
                'label' => 'show.label_pickup_address',
            ])
            ->add('deliveryAddress', null, [
                'label' => 'show.label_dropout_address',
            ])
            ->add('pdfRelativePath', null, [
                'label' => 'show.label_invoice_pdf_path',
                'template' => 'admin/invoice/show_pdf_path.html.twig',
            ])
            ->add('pdfError', null, [
                'label' => 'show.label_invoice_pdf_error',
            ])
            ->add('emailError', null, [
                'label' => 'show.label_invoice_email_error',
            ])
            ->add('orderOffer', null, [
                'label' => 'show.label_offers',
            ])
            ->add('relatedOrder', null, [
                'label' => 'show.label_order',
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }
}
