<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\NotificationLog;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NotificationLogAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('create')
            ->remove('edit')
            ->remove('delete')
            ->remove('export')
            ->add('replay');
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('relatedOrder', ModelFilter::class, [
                'label' => 'filter.label_order',
            ])
            ->add('orderNumber', CallbackFilter::class, [
                'label' => 'filter.label_order_number',
                'field_type' => TextType::class,
                'callback' => static function (ProxyQueryInterface $query, string $alias, string $field, FilterData $data): bool {
                    if (!$data->hasValue() || $data->getValue() === null || $data->getValue() === '') {
                        return false;
                    }

                    $raw = trim((string) $data->getValue());
                    $digits = preg_replace('/\D+/', '', $raw) ?? '';
                    if ($digits === '') {
                        return false;
                    }

                    $query
                        ->leftJoin($alias . '.relatedOrder', 'ord_filter')
                        ->andWhere('ord_filter.orderNumber = :order_number_filter')
                        ->setParameter('order_number_filter', (int) $digits);

                    return true;
                },
            ])
            ->add('eventKey', null, [
                'label' => 'list.label_notification_event_key',
            ])
            ->add('status', null, [
                'label' => 'list.label_notification_log_status',
            ])
            ->add('recipientEmail', null, [
                'label' => 'list.label_notification_recipient_email',
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('relatedOrder', null, [
                'label' => 'list.label_order',
                'template' => 'admin/CRUD/list_order_reference.html.twig',
            ])
            ->add('notificationRule', null, [
                'label' => 'list.label_notification_rule',
            ])
            ->add('eventKey', null, [
                'label' => 'list.label_notification_event_key',
            ])
            ->add('recipientEmail', null, [
                'label' => 'list.label_notification_recipient_email',
            ])
            ->add('status', null, [
                'label' => 'list.label_notification_log_status',
            ])
            ->add('sentAt', 'datetime', [
                'label' => 'list.label_notification_sent_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ])
            ->add('errorMessage', null, [
                'label' => 'list.label_notification_error',
            ])
            ->add('createdAt', 'datetime', [
                'label' => 'list.label_created_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        if ($this->hasSameRole()) {
            $list->add('_action', 'actions', [
                'actions' => [
                    'show' => [],
                    'replay' => [
                        'template' => 'admin/notification_log/list__action_replay.html.twig',
                    ],
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
                'route' => ['name' => 'show'],
            ])
            ->add('notificationRule', null, [
                'label' => 'show.label_notification_rule',
            ])
            ->add('eventKey', null, [
                'label' => 'show.label_notification_event_key',
            ])
            ->add('recipientType', null, [
                'label' => 'show.label_notification_recipient_type',
            ])
            ->add('recipientEmail', null, [
                'label' => 'show.label_notification_recipient_email',
            ])
            ->add('subjectRendered', null, [
                'label' => 'show.label_notification_subject_rendered',
            ])
            ->add('bodyRendered', null, [
                'label' => 'show.label_notification_body_rendered',
            ])
            ->add('attachmentType', null, [
                'label' => 'show.label_notification_attachment_type',
            ])
            ->add('status', null, [
                'label' => 'show.label_notification_log_status',
            ])
            ->add('errorMessage', null, [
                'label' => 'show.label_notification_error',
            ])
            ->add('providerMessageId', null, [
                'label' => 'show.label_notification_provider_message_id',
            ])
            ->add('sentAt', null, [
                'label' => 'show.label_notification_sent_at',
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }
}
