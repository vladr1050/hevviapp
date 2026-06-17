<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enum\WaitingListApplicantType;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class WaitingListApplicantAdmin extends BaseAdmin
{
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
        $typeChoices = [];
        foreach (WaitingListApplicantType::cases() as $case) {
            $typeChoices[$case->value] = $case->value;
        }

        $datagrid
            ->add('email')
            ->add('type', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' => ['choices' => $typeChoices],
            ]);
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('email')
            ->add('phone')
            ->add('type')
            ->add('createdAt', 'datetime', [
                'format' => self::BASE_LIST_DATETIME_FORMAT,
            ]);

        if ($this->hasSameRole()) {
            $list->add('_action', 'actions', [
                'actions' => ['show' => []],
            ]);
        }
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('email')
            ->add('phone')
            ->add('type')
            ->add('createdAt')
            ->add('updatedAt');
    }
}
