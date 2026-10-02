<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

   /**
    * @return Media[]
    */
    public function findByAlbum(Album $value): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.album = :val')
            ->setParameter('val', $value)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
    * @return Media[]
    */
    public function findByUser(?User $user): array
    {
        return $this->findBy(['user' => $user], ['id' => 'ASC']);
    }

//    public function findOneBySomeField($value): ?Media
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
