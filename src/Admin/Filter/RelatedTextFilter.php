<?php

namespace App\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;

final class RelatedTextFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $association, string $field, string $label): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($association.'.'.$field)
            ->setLabel($label)
            ->setFormType(TextFilterType::class);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $parts = explode('.', $filterDataDto->getProperty());
        $field = array_pop($parts);
        $parentAlias = $filterDataDto->getEntityAlias();
        foreach ($parts as $index => $association) {
            $joinAlias = 'rtn_'.$filterDataDto->getParameterName().'_'.$index;
            $queryBuilder->leftJoin($parentAlias.'.'.$association, $joinAlias);
            $parentAlias = $joinAlias;
        }
        $queryBuilder
            ->andWhere(sprintf('%s.%s %s :%s', $parentAlias, $field, $filterDataDto->getComparison(), $filterDataDto->getParameterName()))
            ->setParameter($filterDataDto->getParameterName(), $filterDataDto->getValue());
    }
}
