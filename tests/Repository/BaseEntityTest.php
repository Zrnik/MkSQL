<?php declare(strict_types=1);
/**
 * @author Štěpán Zrník <stepan.zrnik@gmail.com>
 * @copyright Copyright (c) 2021, Štěpán Zrník
 * @project MkSQL <https://github.com/Zrnik/MkSQL>
 */

namespace Tests\Repository;

use Brick\DateTime\LocalDateTime;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\MissingPrimaryKeyEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\MissingTableNameEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\MultiplePrimaryKeysDefined;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\NonNullablePrimaryKeyEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\NullableButWithoutNullAsDefaultPrimaryKeyEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\SubEntityNotPointingBack\EntityNotPointingBack;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\BadEntities\SubEntityNotPointingBack\MainEntity;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\Invoice;
use Tests\Mock\BaseRepositoryAndBaseEntity\Entities\Receiver;
use Tests\Mock\PDO;
use Zrnik\MkSQL\Exceptions\MissingForeignKeyDefinitionInEntityException;
use Zrnik\MkSQL\Exceptions\PrimaryKeyDefinitionException;
use Zrnik\MkSQL\Exceptions\RequiredClassAttributeMissingException;
use Zrnik\MkSQL\Updater;
use Zrnik\PHPUnit\Exceptions;

class BaseEntityTest extends TestCase
{
    use Exceptions;

    public function testConversion(): void
    {
        $receiver = Receiver::fromIterable([
            'receiverPrivateKey' => 'some_receiver',
            'receiverName' => 'Some Receiver'
        ]);

        $invoice = Invoice::fromIterable([
            'invoiceToken' => '123456something',
            'invoiceCurrency' => 'CZK',
            'createDate' => '2000-10-05T10:11:12',
            'invoiceItems' => [],
        ]);

        $invoice->receiver = $receiver;

        $createDateProperty = $invoice::propertyReflection('createDate');

        /** @var ReflectionNamedType|null $type */
        $type = $createDateProperty?->getType();

        static::assertEquals(
            LocalDateTime::class,
            $type?->getName() ?? 'null'
        );

        // was LocalDateTime converted successfully?
        static::assertTrue(
            $invoice->createDate->isEqualTo(
                LocalDateTime::of(
                    2000, 10, 5,
                    10, 11, 12
                )
            )
        );

        static::assertSame([
            'receiverPrivateKey' => 'some_receiver',
            'receiverName' => 'Some Receiver',
        ], $receiver->toArray());

        static::assertSame([
            'invoiceId' => null,
            'invoiceToken' => '123456something',
            'invoiceCurrency' => 'CZK',
            'createDate' => '2000-10-05T10:11:12',
            'receiver' => 'some_receiver',
            'nullableProp' => null,
            'nonNullableProp' => ''
        ], $invoice->toArray());

    }

    public function testBadEntities(): void
    {

        $updater = new Updater(new PDO());

        $this->assertExceptionThrown(
            RequiredClassAttributeMissingException::class,
            function () use ($updater) {
                $updater->use(MissingTableNameEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function () use ($updater) {
                $updater->use(MissingPrimaryKeyEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function () use ($updater) {
                $updater->use(NonNullablePrimaryKeyEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function () use ($updater) {
                $updater->use(NullableButWithoutNullAsDefaultPrimaryKeyEntity::class);
            }
        );

        $this->assertExceptionThrown(
            PrimaryKeyDefinitionException::class,
            function () use ($updater) {
                $updater->use(MultiplePrimaryKeysDefined::class);
            }
        );

        $this->assertExceptionThrown(
            MissingForeignKeyDefinitionInEntityException::class,
            function () use ($updater) {
                $updater->use(MainEntity::class);
                $updater->use(EntityNotPointingBack::class);
            }
        );

    }

}
