<?php
/**
 * SIA SLYFOX Confidential
 *
 * Copyright (C) 2026 SIA SLYFOX.
 * All Rights Reserved.
 *
 * NOTICE:  All information contained herein is, and remains
 * the property of SIA SLYFOX, its suppliers and Customers,
 * if any.  The intellectual and technical concepts contained
 * herein are proprietary to SIA SLYFOX
 * its Suppliers and Customers are protected by trade secret or copyright law.
 *
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained.
 */

namespace App\Admin;

use App\Entity\Order;
use App\Entity\OrderAssignment;
use App\Entity\OrderHistory;
use App\Entity\OrderOffer;
use App\Entity\ServiceArea;
use App\Form\Type\AddressWithMapType;
use App\Form\Type\OrderStatusChoiceType;
use App\Service\OrderAttachmentUploader;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\AdminType;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\DoctrineORMAdminBundle\Filter\CallbackFilter;
use Sonata\Form\Type\CollectionType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Sonata\AdminBundle\Filter\Model\FilterData;
use Symfony\Component\DependencyInjection\Attribute\Required;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Doctrine\ORM\Query\Expr\Join;

class OrderAdmin extends BaseAdmin
{
    private OrderAttachmentUploader $attachmentUploader;

    /**
     * Инъекция через calls в sonata_admin.yaml, т.к. Admin-классы исключены
     * из autowiring и имеют ограниченный конструктор (Sonata AbstractAdmin).
     */
    #[Required]
    public function setAttachmentUploader(OrderAttachmentUploader $uploader): void
    {
        $this->attachmentUploader = $uploader;
    }

    // ------------------------------------------------------------------ hooks

    /**
     * @param Order $object
     */
    protected function prePersist(object $object): void
    {
        $this->handleUploadedFiles($object);
        if ($object instanceof Order) {
            $this->ensureDeliveredHistoryIfMissing($object);
        }
    }

    /**
     * @param Order $object
     */
    protected function preUpdate(object $object): void
    {
        $this->handleUploadedFiles($object);
        if ($object instanceof Order) {
            $this->ensureDeliveredHistoryIfMissing($object);
        }
    }

    /**
     * When status is DELIVERED (set in admin), ensure a history row exists so
     * carrier/sender timelines and delivered_date resolve correctly.
     */
    private function ensureDeliveredHistoryIfMissing(Order $order): void
    {
        if ($order->getStatus() !== Order::STATUS['DELIVERED']) {
            return;
        }

        foreach ($order->getHistories() as $history) {
            if ($history->getStatus() === Order::STATUS['DELIVERED']) {
                return;
            }
        }

        $history = new OrderHistory();
        $history->setRelatedOrder($order);
        $history->setStatus(Order::STATUS['DELIVERED']);
        $history->setChangedBy(OrderHistory::CHANGED_BY['MANUAL']);
        $order->addHistory($history);
    }

    private function handleUploadedFiles(Order $order): void
    {
        /** @var UploadedFile[]|UploadedFile|null $files */
        $files = $this->getForm()->get('uploadedFiles')->getData();

        if (empty($files)) {
            return;
        }

        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $this->attachmentUploader->upload($file, $order);
        }
    }

    // ------------------------------------------------------------------ query

    protected function configureQuery(ProxyQueryInterface $query): ProxyQueryInterface
    {
        $query = parent::configureQuery($query);

        $rootAlias = current($query->getRootAliases());
        $query
            ->leftJoin(sprintf('%s.offers', $rootAlias), 'offers')
            ->addSelect('offers');

        return $query;
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection
            ->remove('delete')
            ->remove('export');
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('orderNumber', null, [
                'label' => 'filter.label_order_number',
            ])
            ->add('status', ChoiceFilter::class, [
                'label' => 'filter.label_status',
                'field_type' => OrderStatusChoiceType::class,
                'field_options' => [
                    'show_counts' => true,
                ],
            ])
            ->add('sender', ModelFilter::class, [
                'label' => 'filter.label_sender',
                'field_type' => ModelAutocompleteType::class,
                'field_options' => [
                    'property' => ['firstName', 'lastName', 'email', 'phone'],
                    'to_string_callback' => function ($entity) {
                        return sprintf('%s %s (%s)', $entity->getFirstName(), $entity->getLastName(), $entity->getEmail());
                    },
                    'callback' => function ($admin, $property, $value) {
                        $datagrid = $admin->getDatagrid();
                        $queryBuilder = $datagrid->getQuery();
                        $queryBuilder
                            ->andWhere(
                                $queryBuilder->expr()->orX(
                                    $queryBuilder->expr()->like('LOWER(o.email)', ':search'),
                                    $queryBuilder->expr()->like('LOWER(o.phone)', ':search'),
                                    $queryBuilder->expr()->like('LOWER(o.firstName)', ':search'),
                                    $queryBuilder->expr()->like('LOWER(o.lastName)', ':search'),
                                    $queryBuilder->expr()->like(
                                        "LOWER(CONCAT(o.firstName, ' ', o.lastName))",
                                        ':search'
                                    )
                                )
                            )
                            ->setParameter('search', '%' . mb_strtolower($value) . '%');
                    },
                ],
            ])
            ->add('carrier', ModelFilter::class, [
                'label' => 'filter.label_carrier',
                'field_type' => ModelAutocompleteType::class,
                'field_options' => [
                    'property' => ['email', 'phone'],
                    'to_string_callback' => function ($entity) {
                        return $entity->getEmail();
                    },
                    'callback' => function ($admin, $property, $value) {
                        $datagrid = $admin->getDatagrid();
                        $queryBuilder = $datagrid->getQuery();
                        $queryBuilder
                            ->andWhere(
                                $queryBuilder->expr()->orX(
                                    $queryBuilder->expr()->like('LOWER(o.email)', ':search'),
                                    $queryBuilder->expr()->like('LOWER(o.phone)', ':search')
                                )
                            )
                            ->setParameter('search', '%' . mb_strtolower($value) . '%');
                    },
                ],
            ])
            ->add('latestOfferStatus', CallbackFilter::class, [
                'label' => 'filter.label_latest_offer_status',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => OrderOffer::STATUS,
                    'choice_translation_domain' => 'AppBundle',
                ],
                'callback' => function (ProxyQueryInterface $queryBuilder, string $alias, string $field, FilterData $data) {
                    if (!$data->hasValue() || $data->getValue() === null || $data->getValue() === '') {
                        return false;
                    }

                    $statusValue = $data->getValue();

                    // JOIN с offers для фильтрации
                    $queryBuilder
                        ->innerJoin($alias . '.offers', 'latest_offer_filter')
                        ->andWhere('latest_offer_filter.status = :offer_status')
                        // Проверяем, что это последний offer (нет более нового offer для этого Order)
                        ->andWhere('NOT EXISTS (
                            SELECT 1 FROM ' . OrderOffer::class . ' newer_offer
                            WHERE newer_offer.relatedOrder = ' . $alias . '
                            AND newer_offer.createdAt > latest_offer_filter.createdAt
                        )')
                        ->setParameter('offer_status', $statusValue);

                    return true;
                },
            ])
            ->add('lastAssignmentStatus', CallbackFilter::class, [
                'label' => 'filter.label_last_assignment_status',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => OrderAssignment::STATUS,
                    'choice_translation_domain' => 'AppBundle',
                ],
                'callback' => function (ProxyQueryInterface $queryBuilder, string $alias, string $field, FilterData $data) {
                    if (!$data->hasValue() || $data->getValue() === null || $data->getValue() === '') {
                        return false;
                    }

                    $statusValue = $data->getValue();

                    // JOIN с orderAssignments для фильтрации
                    $queryBuilder
                        ->innerJoin($alias . '.orderAssignments', 'latest_assignment_filter')
                        ->andWhere('latest_assignment_filter.status = :assignment_status')
                        // Проверяем, что это последний assignment (нет более нового assignment для этого Order)
                        ->andWhere('NOT EXISTS (
                            SELECT 1 FROM ' . OrderAssignment::class . ' newer_assignment
                            WHERE newer_assignment.relatedOrder = ' . $alias . '
                            AND newer_assignment.createdAt > latest_assignment_filter.createdAt
                        )')
                        ->setParameter('assignment_status', $statusValue);

                    return true;
                },
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('reference', TextType::class, [
                'label'    => 'list.label_order_number',
                'accessor' => static fn(Order $order): string => $order->getReference(),
            ])
            ->add('sender', null, [
                'associated_property' => function ($user) {
                    return sprintf('%s %s', $user->getFirstName(), $user->getLastName());
                },
            ])
            ->add('carrier', null, [
                'associated_property' => 'legalName',
            ])
            ->add('status', TextType::class, [
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    return ucfirst(strtolower(array_flip(Order::STATUS)[$object->getStatus()]));
                },
            ])
            ->add('_addresses', null, [
                'label' => 'list.label_addresses',
                'template' => 'admin/CRUD/list_addresses_combined.html.twig',
                'virtual_field' => true,
            ])
            ->add('_order_assignments', null, [
                'label' => 'list.label_order_assignments',
                'template' => 'admin/CRUD/list_order_assignments.html.twig',
                'virtual_field' => true,
            ])
            ->add('cargo', null, [
                'label' => 'list.label_cargo_count',
                'template' => 'admin/CRUD/list_collection_count.html.twig',
            ])
            ->add('_delivery_gross_price', null, [
                'label' => 'list.label_delivery_gross_price',
                'template' => 'admin/CRUD/list_delivery_gross_price.html.twig',
                'virtual_field' => true,
            ])
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('reference', TextType::class, [
                'label'    => 'show.label_order_number',
                'accessor' => static fn(Order $order): string => $order->getReference(),
            ])
            ->add('status', TextType::class, [
                'catalogue' => 'AppBundle',
                'accessor' => static function ($object): string {
                    return ucfirst(strtolower(array_flip(Order::STATUS)[$object->getStatus()]));
                },
            ])
            ->add('sender', null, [
                'associated_property' => function ($carrier) {
                    return sprintf('%s %s (%s)', $carrier->getFirstName(), $carrier->getLastName(), $carrier->getEmail());
                },
            ])
            ->add('carrier')
            ->add('vehiclePlate', null, [
                'label' => 'show.label_vehicle_plate',
            ])
            ->add('pickupAddress', null, [
                'template' => 'admin/CRUD/show_address_with_map.html.twig',
            ])
            ->add('dropoutAddress', null, [
                'template' => 'admin/CRUD/show_address_with_map.html.twig',
            ])
            ->add('notes')
            ->add('cancelReason')
            ->add('pickupDate', null, [
                'format' => 'M d, Y',
            ])
            ->add('pickupTimeFrom', null, [
                'format' => 'H:m',
            ])
            ->add('pickupTimeTo', null, [
                'format' => 'H:m',
            ])
            ->add('deliveryDate', null, [
                'format' => 'M d, Y'
            ])
            ->add('deliveryTimeFrom', null, [
                'format' => 'H:m',
            ])
            ->add('deliveryTimeTo', null, [
                'format' => 'H:m',
            ])
            ->add('stackable')
            ->add('manipulatorNeeded')
            ->add('cargo')
            ->add('offers', null, [
                'label' => 'show.label_offers',
            ])
            ->add('orderAssignments', null, [
                'label' => 'show.label_order_assignments',
            ])
            ->add('histories', null, [
                'label' => 'show.label_status_history',
            ])
            ->add('attachments', null, [
                'label'    => 'show.label_attachments',
                'template' => 'admin/CRUD/show_order_attachments.html.twig',
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->tab('tabs_general')
            ->with('order_info', [
                'class' => 'col-md-6',
            ])
            ->add('_orderNumber', TextType::class, [
                'mapped'   => false,
                'required' => false,
                'disabled' => true,
                'label'    => 'form.label_order_number',
                'data'     => (function (): string {
                    $subject = $this->getSubject();
                    return $subject instanceof Order ? $subject->getReference() : 'HVY-?????';
                })(),
                'help'     => 'form.label_help_order_number',
                'attr'     => ['style' => 'font-weight: bold; font-size: 1.1em;'],
            ])
            ->add('sender', ModelAutocompleteType::class, [
                'required' => true,
                'property' => ['firstName', 'lastName', 'email', 'phone'],
                'to_string_callback' => function ($entity) {
                    return sprintf('%s %s (%s)', $entity->getFirstName(), $entity->getLastName(), $entity->getEmail());
                },
                'callback' => function ($admin, $property, $value) {
                    $datagrid = $admin->getDatagrid();
                    $queryBuilder = $datagrid->getQuery();
                    $queryBuilder
                        ->andWhere(
                            $queryBuilder->expr()->orX(
                                $queryBuilder->expr()->like('LOWER(o.email)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.phone)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.firstName)', ':search'),
                                $queryBuilder->expr()->like('LOWER(o.lastName)', ':search'),
                                $queryBuilder->expr()->like(
                                    "LOWER(CONCAT(o.firstName, ' ', o.lastName))",
                                    ':search'
                                )
                            )
                        )
                        ->setParameter('search', '%' . mb_strtolower($value) . '%');
                },
            ])
            ->add('carrier', TextType::class, [
                'mapped'   => false,
                'required' => false,
                'disabled' => true,
                'data'     => (function (): string {
                    $subject = $this->getSubject();
                    if ($subject instanceof Order && $subject->getCarrier() !== null) {
                        return $subject->getCarrier()->getLegalName();
                    }
                    return '';
                })(),
                'help'     => 'form.label_help_carrier_readonly',
                'attr'     => ['placeholder' => '—'],
            ])
            ->add('currency', ChoiceType::class, [
                'choices' => ServiceArea::CURRENCY,
                'help' => 'form.label_help_order_currency',
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => Order::STATUS,
                'choice_translation_domain' => 'AppBundle',
                'required' => true,
                'choice_attr' => static fn(int $val): array => $val === Order::STATUS['ASSIGNED']
                    ? ['disabled' => 'disabled', 'title' => 'Assigned status is managed automatically via Order Assignments']
                    : [],
            ])
            ->add('vehiclePlate', TextType::class, [
                'required' => false,
                'attr' => ['maxlength' => 32],
            ])
            ->end()
            ->with('addresses', [
                'class' => 'col-md-6',
            ])
            ->add('pickupAddress', AddressWithMapType::class, [
                'required' => true,
                'enable_map' => true,
                'map_button_icon' => 'fas fa-map-marker-alt',
                'modal_id' => 'pickup_address_map_modal',
            ])
            ->add('dropoutAddress', AddressWithMapType::class, [
                'required' => true,
                'enable_map' => true,
                'map_button_icon' => 'fas fa-map-marker-alt',
                'modal_id' => 'dropout_address_map_modal',
            ])
            ->add('dropoutLatitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('dropoutLongitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('pickupLatitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('pickupLongitude', HiddenType::class, [
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
            ])
            ->add('cancelReason', TextType::class, [
                'required' => false,
                'attr'     => ['maxlength' => 255],
                'help'     => 'form.label_help_cancel_reason',
            ])
            ->end()
            ->with('schedule', [
                'class' => 'col-md-12',
            ])
            ->add('pickupDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('pickupTimeFrom', TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('pickupTimeTo', TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('deliveryDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('deliveryTimeFrom', TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('deliveryTimeTo', TimeType::class, [
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->end()
            ->with('order_cargo_requirements', [
                'class' => 'col-md-12',
            ])
            ->add('stackable', CheckboxType::class, [
                'required' => false,
            ])
            ->add('manipulatorNeeded', CheckboxType::class, [
                'required' => false,
            ])
            ->end()
            ->end()
            ->tab('tabs_files')
            ->with('files_upload', [
                'class' => 'col-md-12',
            ])
            ->add('uploadedFiles', FileType::class, [
                'mapped'   => false,
                'required' => false,
                'multiple' => true,
                'label'    => 'form.label_upload_files',
                'attr'     => ['accept' => 'application/pdf'],
                'help'     => 'form.label_help_upload_pdf',
            ])
            ->end()
            ->end()
            ->tab('tabs_cargo')
            ->with('cargo_list', [
                'class' => 'col-md-12',
            ])
            ->add('cargo', CollectionType::class, [
                'by_reference' => false,
                'type' => AdminType::class,
                'label' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ])
            ->end()
            ->end()
            ->tab('tabs_offers')
            ->with('offers_list', [
                'class' => 'col-md-12',
            ])
            ->add('offers', CollectionType::class, [
                'by_reference' => false,
                'type' => AdminType::class,
                'label' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ])
            ->end()
            ->end()
            ->tab('tabs_assigment')
            ->with('assigment_list', [
                'class' => 'col-md-12',
            ])
            ->add('orderAssignments', CollectionType::class, [
                'by_reference' => false,
                'type' => AdminType::class,
                'label' => false,
            ], [
                'edit' => 'inline',
                'inline' => 'table',
            ])
            ->end()
            ->end();
    }
}
