<?php

declare(strict_types=1);

namespace App\Form\Type;

use App\Enum\OfferPriceAdjustmentMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class OfferPriceAdjustmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', EnumType::class, [
                'class' => OfferPriceAdjustmentMode::class,
                'label' => 'order_offer.adjust.label_mode',
                'choice_label' => static fn (OfferPriceAdjustmentMode $mode): string => 'order_offer.adjust.mode.'.$mode->value,
                'translation_domain' => 'AppBundle',
            ])
            ->add('numericValue', NumberType::class, [
                'label' => 'order_offer.adjust.label_value',
                'scale' => 2,
                'html5' => true,
                'required' => true,
                'help' => 'order_offer.adjust.help_value',
                'translation_domain' => 'AppBundle',
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'order_offer.adjust.label_reason',
                'required' => true,
                'attr' => ['rows' => 4],
                'constraints' => [
                    new NotBlank(message: 'order_offer.adjust.error.reason_required'),
                    new Length(min: 3, minMessage: 'order_offer.adjust.error.reason_required'),
                ],
                'translation_domain' => 'AppBundle',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'AppBundle',
        ]);
    }
}
