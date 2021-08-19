<?php

namespace Repository;

use Brick\DateTime\LocalDateTime;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\MissingPrimaryKeyEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\MissingTableNameEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\MultiplePrimaryKeysDefined;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\NonNullablePrimaryKeyEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\NullableButWithoutNullAsDefaultPrimaryKeyEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\SubEntityNotPointingBack\EntityNotPointingBack;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\SubEntityNotPointingBack\MainEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\ValidEntitiesButPutThemInWrongOrder\ValidMainEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\ValidEntitiesButPutThemInWrongOrder\ValidSubEntity;
use Mock\BaseRepositoryAndBaseEntity\Entities\Invoice;
use Mock\BaseRepositoryAndBaseEntity\Entities\Receiver;
use Mock\PDO;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Zrnik\MkSQL\Exceptions\InvalidArgumentException;
use Zrnik\MkSQL\Exceptions\InvalidEntityOrderException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyDefinitionException;
use Zrnik\MkSQL\Exceptions\ReflectionFailedException;
use Zrnik\MkSQL\Exceptions\RequiredClassAttributeMissingException;
use Zrnik\MkSQL\Repository\MissingForeignKeyDefinitionInEntityException;
use Zrnik\MkSQL\Updater;
use Zrnik\PHPUnit\Exceptions;

class BaseEntityTest extends TestCase
{
    use Exceptions;

    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws PrimaryKeyDefinitionException
     * @throws ReflectionFailedException
     */
    public function testConversion(): void
    {
        $receiver = Receiver::fromIterable([
            "receiverPrivateKey" => "some_receiver",
            "receiverName" => "Some Receiver"
        ]);

        $invoice = Invoice::fromIterable([
            "invoiceToken" => "123456something",
            "invoiceCurrency" => "CZK",
            "createDate" => "2000-10-05T10:11:12",
            "invoiceItems" => [],
        ]);

        $invoice->receiver = $receiver;

        // was LocalDateTime converted successfully?
        $this->assertTrue(
            $invoice->createDate->isEqualTo(
                LocalDateTime::of(
                    2000,10,5,
                    10,11,12
                )
            )
        );

        $this->assertSame([
            'receiverPrivateKey' => 'some_receiver',
            'receiverName' => 'Some Receiver',
        ],$receiver->toArray());

        $this->assertSame([
            'invoiceId' => null,
            'invoiceToken' => '123456something',
            'invoiceCurrency' => 'CZK',
            'createDate' => '2000-10-05T10:11:12',
            'receiver' => 'some_receiver'
        ],$invoice->toArray());

    }

    public function testBadEntities(): void {

        $updater = new Updater(new PDO());

        $this->assertExceptionThrown(
            RequiredClassAttributeMissingException::class,
            function() use ($updater) {
                $updater->use(MissingTableNameEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function() use ($updater) {
                $updater->use(MissingPrimaryKeyEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function() use ($updater) {
                $updater->use(NonNullablePrimaryKeyEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function() use ($updater) {
                $updater->use(NullableButWithoutNullAsDefaultPrimaryKeyEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function() use ($updater) {
                $updater->use(MultiplePrimaryKeysDefined::class);
            }
        );

        $this->assertExceptionThrown(
            MissingForeignKeyDefinitionInEntityException::class,
            function() use ($updater) {
                $updater->use(
                    MainEntity::class,
                    EntityNotPointingBack::class
                );
            }
        );

        $this->assertExceptionThrown(
            InvalidEntityOrderException::class,
            function() use ($updater) {
                $updater->use(
                    ValidSubEntity::class,
                    ValidMainEntity::class
                );
            }
        );


    }


}
