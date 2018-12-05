<?php

namespace NetBull\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Class CompoundRangeType
 * @package NetBull\CoreBundle\Form\Type
 */
class CompoundRangeType extends AbstractType implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('min', NumberType::class, [
                'required' => false,
                'constraints' => new Callback([$this, 'validateMin']),
            ])
            ->add('max', NumberType::class, [
                'required' => false,
                'constraints' => new Callback([$this, 'validateMax']),
            ])
            ->addViewTransformer($this)
        ;
    }

    /**
     * @param $value
     * @param ExecutionContextInterface $context
     */
    public function validateMin($value, ExecutionContextInterface $context)
    {
        $rootForm = $context->getRoot();
        $data = $rootForm->getData();

        if ($value > $context->getObject()->getParent()->get('max')->getData()) {
            $context
                ->buildViolation('The min value has to be lower than the max value')
                ->addViolation();
        }
    }

    /**
     * @param $value
     * @param ExecutionContextInterface $context
     */
    public function validateMax($value, ExecutionContextInterface $context)
    {
        $rootForm = $context->getRoot();
        $data = $rootForm->getData();

        if ($value < $context->getObject()->getParent()->get('min')->getData()) {
            $context
                ->buildViolation('The max value has to be higher than the min value')
                ->addViolation();
        }
    }

    /**
     * @inheritdoc
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        $parts = explode('-', $value);

        if (2 !== \count($parts)) {
            return [
                'min' => null,
                'max' => null,
            ];
        }

        return [
            'min' => $parts[0],
            'max' => $parts[1],
        ];
    }

    /**
     * @inheritdoc
     */
    public function reverseTransform($value)
    {
        if (!$value || !\is_array($value) || 2 !== \count($value)) {
            return null;
        }

        return implode('-', $value);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'compound' => true,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'compound_range';
    }
}
