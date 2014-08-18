<?php

namespace Gedmo\Sortable\Mapping\Event\Adapter;

use Gedmo\Mapping\Event\Adapter\ORM as BaseAdapterORM;
use Gedmo\Sortable\Mapping\Event\SortableAdapter;

/**
 * Doctrine event adapter for ORM adapted
 * for sortable behavior
 *
 * @author Lukas Botsch <lukas.botsch@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
final class ORM extends BaseAdapterORM implements SortableAdapter
{

    public function getMaxPosition(array $config, $meta, $groups)
    {
        $em = $this->getObjectManager();
        
        $qb = $em->createQueryBuilder();
        $qb->select('MAX(n.'.$config['position'].')')
           ->from($config['useObjectClass'], 'n');
        $qb = $this->addGroupWhere($qb, $groups);
        $query = $qb->getQuery();
        $query->useQueryCache(false);
        $query->useResultCache(false);
        $res = $query->getResult();
        
        return $res[0][1];
    }

    private function addGroupWhere($qb, $groups)
    {
        $i = 1;
        foreach ($groups as $group => $value) {
            $whereFunc = is_null($qb->getDQLPart('where')) ? 'where' : 'andWhere';
            if (is_null($value)) {
                $qb->{$whereFunc}($qb->expr()->isNull('n.'.$group));
            } else {
                $qb->{$whereFunc}('n.'.$group.' = :group__'.$i);
                $qb->setParameter('group__'.$i, $value);
            }
            $i++;
        }
        return $qb;
    }

    public function updatePositions($relocation, $delta, $config)
    {
        $sign = $delta['delta'] < 0 ? "-" : "+";
        $absDelta = abs($delta['delta']);
        $dql = "UPDATE {$relocation['name']} n";
        $dql .= " SET n.{$config['position']} = n.{$config['position']} {$sign} {$absDelta}";
        $dql .= " WHERE n.{$config['position']} >= {$delta['start']}";
        // if not null, false or 0
        if ($delta['stop'] > 0) {
            $dql .= " AND n.{$config['position']} < {$delta['stop']}";
        }
        $i = -1;
        $params = array();
        foreach ($relocation['groups'] as $group => $value) {
            if (is_null($value)) {
                $dql .= " AND n.{$group} IS NULL";
            } else {
                $dql .= " AND n.{$group} = :val___".(++$i);
                $params['val___'.$i] = $value;
            }
        }
        
        $em = $this->getObjectManager();
        $q = $em->createQuery($dql);
        $q->setParameters($params);
        $q->getSingleScalarResult();
    }

}