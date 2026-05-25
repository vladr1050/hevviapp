<?php

declare(strict_types=1);

namespace App\Admin;

use App\Entity\PricingSettings;
use FRPC\SonataAuthorization\Admin\BaseAdmin;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class PricingSettingsAdmin extends BaseAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        parent::configureRoutes($collection);
        $collection->remove('delete');
        $collection->remove('export');
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('defaultPriceCoefficient', null, ['label' => 'show.label_default_price_coefficient'])
            ->add('createdAt')
            ->add('updatedAt');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->with('pricing_settings', ['class' => 'col-md-12', 'label' => 'form.group_appsettings_pricing'])
            ->add('defaultPriceCoefficient', NumberType::class, [
                'required' => true,
                'scale' => 4,
                'html5' => true,
                'label' => 'form.label_default_price_coefficient',
                'help' => 'form.help_default_price_coefficient',
            ])
            ->end();
    }

    protected function prePersist(object $object): void
    {
        $this->assertSingleton($object);
    }

    private function assertSingleton(object $object): void
    {
        if (!$object instanceof PricingSettings) {
            return;
        }
        $em = $this->getModelManager()->getEntityManager(PricingSettings::class);
        $count = $em->getRepository(PricingSettings::class)->count([]);
        if ($count > 0) {
            throw new \RuntimeException('Only one PricingSettings record is allowed. Edit the existing row.');
        }
    }
}
