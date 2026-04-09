<?php

declare(strict_types=1);

namespace App\Admin;

use App\Notification\NotificationEventKey;
use App\Notification\NotificationRecipientType;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class NotificationRuleAdmin extends BaseAdmin
{
    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues[DatagridInterface::SORT_BY] = 'updatedAt';
        $sortValues[DatagridInterface::SORT_ORDER] = 'desc';

        parent::configureDefaultSortValues($sortValues);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid
            ->add('name')
            ->add('eventKey', null, [
                'label' => 'list.label_notification_event_key',
            ])
            ->add('recipientType', null, [
                'label' => 'list.label_notification_recipient_type',
            ])
            ->add('isActive', null, [
                'label' => 'list.label_notification_is_active',
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('name', null, [
                'label' => 'list.label_notification_rule_name',
            ])
            ->add('eventKey', null, [
                'label' => 'list.label_notification_event_key',
            ])
            ->add('recipientType', null, [
                'label' => 'list.label_notification_recipient_type',
            ])
            ->add('isActive', null, [
                'label' => 'list.label_notification_is_active',
            ])
            ->add('attachInvoicePdf', null, [
                'label' => 'list.label_notification_attach_invoice',
            ])
            ->add('sendOncePerOrder', null, [
                'label' => 'list.label_notification_send_once',
            ])
            ->add('updatedAt', 'datetime', [
                'label' => 'list.label_updated_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);
        parent::configureListFields($list);
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $eventChoices = array_combine(NotificationEventKey::all(), NotificationEventKey::all()) ?: [];
        $recipientChoices = array_combine(NotificationRecipientType::all(), NotificationRecipientType::all()) ?: [];

        $form
            ->tab('tabs_general')
            ->with('group_notification_rule', ['class' => 'col-md-12'])
            ->add('name', TextType::class, [
                'label' => 'form.label_notification_rule_name',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.label_notification_rule_description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'form.label_notification_is_active',
                'required' => false,
            ])
            ->add('eventKey', ChoiceType::class, [
                'label' => 'form.label_notification_event_key',
                'choices' => $eventChoices,
                'required' => true,
            ])
            ->add('recipientType', ChoiceType::class, [
                'label' => 'form.label_notification_recipient_type',
                'choices' => $recipientChoices,
                'required' => true,
            ])
            ->add('subjectTemplate', TextareaType::class, [
                'label' => 'form.label_notification_subject_template',
                'required' => true,
                'attr' => ['rows' => 2],
            ])
            ->add('bodyTemplate', TextareaType::class, [
                'label' => 'form.label_notification_body_template',
                'required' => true,
                'attr' => ['rows' => 16],
            ])
            ->add('attachInvoicePdf', CheckboxType::class, [
                'label' => 'form.label_notification_attach_invoice',
                'required' => false,
            ])
            ->add('sendOncePerOrder', CheckboxType::class, [
                'label' => 'form.label_notification_send_once',
                'required' => false,
            ])
            ->end()
            ->end();
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('name', null, [
                'label' => 'show.label_notification_rule_name',
            ])
            ->add('description', null, [
                'label' => 'show.label_notification_rule_description',
            ])
            ->add('isActive', null, [
                'label' => 'show.label_notification_is_active',
            ])
            ->add('eventKey', null, [
                'label' => 'show.label_notification_event_key',
            ])
            ->add('recipientType', null, [
                'label' => 'show.label_notification_recipient_type',
            ])
            ->add('subjectTemplate', null, [
                'label' => 'show.label_notification_subject_template',
            ])
            ->add('bodyTemplate', null, [
                'label' => 'show.label_notification_body_template',
            ])
            ->add('attachInvoicePdf', null, [
                'label' => 'show.label_notification_attach_invoice',
            ])
            ->add('sendOncePerOrder', null, [
                'label' => 'show.label_notification_send_once',
            ])
            ->add('createdAt')
            ->add('updatedAt');
    }
}
