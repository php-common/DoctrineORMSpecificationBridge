<?php

namespace Common\Specification\Doctrine\ORM\Visitor;

use Common\Specification\Visitor\Visitor;
use Common\Specification\Expression as Expr;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * Loads a specification into a QueryBuilder.
 *
 * @author Marcos Passos <marcos@croct.com>
 */
final class QueryBuilderVisitor implements Visitor
{
    /**
     * @var QueryBuilder
     */
    private $builder;

    /**
     * Constructor.
     *
     * @param QueryBuilder $builder The query builder being manipulated.
     */
    public function __construct(QueryBuilder $builder)
    {
        $this->builder = $builder;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(Expr\Expression $expression)
    {
        $this->builder->where($expression->accept($this));
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When a comparison expression is not supported.
     */
    public function visitComparison(Expr\Comparison $comparison)
    {
        $property = $comparison->getProperty();
        $path = $property->getPath();

        $expr = $this->builder->expr();
        $aliases = $this->builder->getAllAliases();
        $prefix = reset($aliases);

        foreach (array_slice($path, 0, -1) as $alias) {
            if (!in_array($alias, $aliases)) {
                $this->builder->join(sprintf('%s.%s', $prefix, $alias), $alias);
            }

            $prefix = $alias;
        }

        $field = sprintf('%s.%s', $prefix, end($path));
        $value = $comparison->getValue();

        switch (true) {
            case ($comparison instanceof Expr\IdenticalTo):
            case ($comparison instanceof Expr\EqualTo):
                return $expr->eq($field, $expr->literal($value));
            case ($comparison instanceof Expr\NotIdenticalTo):
            case ($comparison instanceof Expr\NotEqualTo):
                return $expr->neq($field, $expr->literal($value));
            case ($comparison instanceof Expr\LessThan):
                return $expr->lt($field, $expr->literal($value));
            case ($comparison instanceof Expr\LessThanOrEqualTo):
                return $expr->lte($field, $expr->literal($value));
            case ($comparison instanceof Expr\GreaterThan):
                return $expr->gt($field, $expr->literal($value));
            case ($comparison instanceof Expr\GreaterThanOrEqualTo):
                return $expr->gte($field, $expr->literal($value));
            case ($comparison instanceof Expr\In):
                return $expr->in($field, $expr->literal($value));
            case ($comparison instanceof Expr\NotIn):
                return $expr->notIn($field, $expr->literal($value));
            case ($comparison instanceof Expr\Contains):
                return $expr->like(sprintf('LOWER(%s)', $field), $expr->literal('%'.strtolower($value).'%'));
        }

        throw new InvalidArgumentException(sprintf(
            'Unsupported comparison operator "%s".',
            get_class($comparison)
        ));
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When a comparison expression is not supported.
     */
    public function visitComposite(Expr\Composite $composite)
    {
        $expressions = [];
        foreach ($composite->getExpressions() as $expression) {
            $expressions[] = $expression->accept($this);
        }

        $expr = $this->builder->expr();

        switch (true) {
            case ($composite instanceof Expr\AndX):
                return call_user_func_array([$expr, 'andX'], $expressions);
            case ($composite instanceof Expr\OrX):
                return call_user_func_array([$expr, 'orX'], $expressions);
            case ($composite instanceof Expr\Not):
                return $expr->not($expressions);
        }

        throw new InvalidArgumentException(sprintf(
            'Unsupported composite operator "%s".',
            get_class($composite)
        ));
    }
}