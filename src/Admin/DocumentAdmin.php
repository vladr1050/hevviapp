<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class DocumentAdmin extends BaseAdmin
{
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues[DatagridInterface::SORT_BY] = 'issuedAt';
        $sortValues[DatagridInterface::SORT_ORDER] = 'DESC';
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('create')
            ->remove('edit')
            ->remove('delete')
            ->remove('export');
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $documentTypeChoices = [];
        foreach (DocumentType::cases() as $case) {
            $documentTypeChoices[$case->value] = $case->value;
        }

        $statusChoices = [];
        foreach (DocumentStatus::cases() as $case) {
            $statusChoices[$case->value] = $case->value;
        }

        $datagrid
            ->add('relatedOrder', ModelFilter::class, [
                'label' => 'filter.label_order',
            ])
            ->add('documentType', ChoiceFilter::class, [
                'label' => 'filter.label_document_type',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => $documentTypeChoices,
                ],
            ])
            ->add('status', ChoiceFilter::class, [
                'label' => 'filter.label_document_status',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => $statusChoices,
                ],
            ])
            ->add('documentNumber', null, [
                'label' => 'filter.label_document_number',
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('relatedOrder', null, [
                'label' => 'list.label_order',
            ])
            ->add('documentType', null, [
                'label' => 'list.label_document_type',
            ])
            ->add('documentNumber', null, [
                'label' => 'list.label_document_number',
            ])
            ->add('status', null, [
                'label' => 'list.label_document_status',
            ])
            ->add('issuedAt', 'datetime', [
                'label' => 'list.label_document_issued_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ])
            ->add('sentAt', 'datetime', [
                'label' => 'list.label_document_sent_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ])
            ->add('_pdf', null, [
                'label' => 'list.label_document_pdf',
                'template' => 'admin/document/list__field_pdf.html.twig',
                'virtual_field' => true,
                'sortable' => false,
            ]);

        if ($this->hasSameRole()) {
            $list->add('_action', 'actions', [
                'actions' => [
                    'show' => [],
                ],
            ]);
        }
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('relatedOrder', null, [
                'label' => 'show.label_order',
            ])
            ->add('documentType', null, [
                'label' => 'show.label_document_type',
            ])
            ->add('documentNumber', null, [
                'label' => 'show.label_document_number',
            ])
            ->add('status', null, [
                'label' => 'show.label_document_status',
            ])
            ->add('filePath', null, [
                'label' => 'show.label_document_file_path',
            ])
            ->add('amountNet', null, [
                'label' => 'show.label_document_amount_net',
            ])
            ->add('amountVat', null, [
                'label' => 'show.label_document_amount_vat',
            ])
            ->add('amountTotal', null, [
                'label' => 'show.label_document_amount_total',
            ])
            ->add('issuedAt', null, [
                'label' => 'show.label_document_issued_at',
            ])
            ->add('sentAt', null, [
                'label' => 'show.label_document_sent_at',
            ])
            ->add('createdAt', null, [
                'label' => 'list.label_created_at',
            ])
            ->add('updatedAt', null, [
                'label' => 'list.label_updated_at',
            ])
            ->add('_pdf_show', null, [
                'label' => 'show.label_document_pdf',
                'template' => 'admin/document/show__field_pdf.html.twig',
                'virtual_field' => true,
            ]);
    }
}
