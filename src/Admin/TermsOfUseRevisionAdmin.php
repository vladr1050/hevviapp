<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\TermsOfUseRevision;
use App\Enum\TermsAudience;
use App\Enum\TermsRevisionStatus;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class TermsOfUseRevisionAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        parent::configureRoutes($collection);
        $collection->remove('export');
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues[DatagridInterface::SORT_BY] = 'version';
        $sortValues[DatagridInterface::SORT_ORDER] = 'DESC';

        parent::configureDefaultSortValues($sortValues);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $audienceChoices = [];
        foreach (TermsAudience::cases() as $case) {
            $audienceChoices[$case->value] = $case->value;
        }
        $statusChoices = [];
        foreach (TermsRevisionStatus::cases() as $case) {
            $statusChoices[$case->value] = $case->value;
        }

        $filter
            ->add('audience', ChoiceFilter::class, [
                'label' => 'filter.label_terms_audience',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => $audienceChoices,
                ],
            ])
            ->add('status', ChoiceFilter::class, [
                'label' => 'filter.label_terms_status',
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => $statusChoices,
                ],
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('audience', null, [
                'label' => 'list.label_terms_audience',
            ])
            ->add('version', null, [
                'label' => 'list.label_terms_version',
            ])
            ->add('status', null, [
                'label' => 'list.label_terms_status',
            ])
            ->add('title', null, [
                'label' => 'list.label_terms_title',
            ])
            ->add('publishedAt', null, [
                'label' => 'list.label_terms_published_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ])
            ->add('createdAt', null, [
                'label' => 'list.label_created_at',
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);

        if ($this->hasSameRole()) {
            $list->add('_action', 'actions', [
                'actions' => [
                    'edit' => [],
                    'show' => [],
                    'delete' => [],
                ],
            ]);
        }
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $subject = $this->getSubject();
        $isEdit = $subject instanceof TermsOfUseRevision && $subject->getId() !== null;

        $form
            ->with('terms', ['label' => 'form.group_terms', 'class' => 'col-md-12'])
            ->add('audience', EnumType::class, [
                'class' => TermsAudience::class,
                'label' => 'form.label_terms_audience',
                'help' => 'form.help_terms_audience',
                'disabled' => $isEdit,
            ])
            ->add('title', TextType::class, [
                'label' => 'form.label_terms_title',
                'required' => true,
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'form.label_terms_subtitle',
                'required' => false,
            ])
            ->add('bodyHtml', TextareaType::class, [
                'label' => 'form.label_terms_body_html',
                'help' => 'form.help_terms_body_html',
                'required' => true,
                'attr' => [
                    'rows' => 24,
                    'style' => 'font-family: monospace; font-size: 12px;',
                ],
            ])
            ->add('status', EnumType::class, [
                'class' => TermsRevisionStatus::class,
                'label' => 'form.label_terms_status',
                'help' => 'form.help_terms_status',
            ])
            ->end();
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id', null, ['label' => 'show.label_terms_id'])
            ->add('audience', null, ['label' => 'show.label_terms_audience'])
            ->add('version', null, ['label' => 'show.label_terms_version'])
            ->add('status', null, ['label' => 'show.label_terms_status'])
            ->add('title', null, ['label' => 'show.label_terms_title'])
            ->add('subtitle', null, ['label' => 'show.label_terms_subtitle'])
            ->add('bodyHtml', null, [
                'label' => 'show.label_terms_body_html',
                'template' => 'admin/terms_of_use_revision/show__body_html.html.twig',
                'safe' => false,
            ])
            ->add('publishedAt', null, [
                'label' => 'show.label_terms_published_at',
                'format' => self::BASE_SHOW_DATETIME_FORMAT,
            ])
            ->add('createdAt', null, [
                'label' => 'list.label_created_at',
                'format' => self::BASE_SHOW_DATETIME_FORMAT,
            ])
            ->add('updatedAt', null, [
                'label' => 'list.label_updated_at',
                'format' => self::BASE_SHOW_DATETIME_FORMAT,
            ]);
    }
}
