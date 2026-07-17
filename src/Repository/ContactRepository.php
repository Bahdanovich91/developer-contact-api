<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function save(Contact $contact, bool $flush = true): void
    {
        $this->getEntityManager()->persist($contact);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return array{
     *     total: int,
     *     by_sentiment: array<string, int>,
     *     by_category: array<string, int>,
     *     last_24h: int,
     *     last_7d: int
     * }
     */
    public function getMetrics(): array
    {
        $em = $this->getEntityManager();

        $total = (int) $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->getQuery()
            ->getSingleScalarResult();

        $bySentimentRows = $em->createQueryBuilder()
            ->select('COALESCE(c.sentiment, \'unknown\') AS sentiment, COUNT(c.id) AS cnt')
            ->from(Contact::class, 'c')
            ->groupBy('sentiment')
            ->getQuery()
            ->getArrayResult();

        $byCategoryRows = $em->createQueryBuilder()
            ->select('COALESCE(c.category, \'other\') AS category, COUNT(c.id) AS cnt')
            ->from(Contact::class, 'c')
            ->groupBy('category')
            ->getQuery()
            ->getArrayResult();

        $last24h = (int) $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->where('c.createdAt >= :since')
            ->setParameter('since', new \DateTimeImmutable('-24 hours'))
            ->getQuery()
            ->getSingleScalarResult();

        $last7d = (int) $em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Contact::class, 'c')
            ->where('c.createdAt >= :since')
            ->setParameter('since', new \DateTimeImmutable('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $bySentiment = [];
        foreach ($bySentimentRows as $row) {
            $bySentiment[(string) $row['sentiment']] = (int) $row['cnt'];
        }

        $byCategory = [];
        foreach ($byCategoryRows as $row) {
            $byCategory[(string) $row['category']] = (int) $row['cnt'];
        }

        return [
            'total' => $total,
            'by_sentiment' => $bySentiment,
            'by_category' => $byCategory,
            'last_24h' => $last24h,
            'last_7d' => $last7d,
        ];
    }
}
