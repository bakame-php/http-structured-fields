<?php

declare(strict_types=1);

namespace Validation;

use Bakame\Http\StructuredFields\Validation\ErrorCode;
use Bakame\Http\StructuredFields\Validation\Violation;
use Bakame\Http\StructuredFields\Validation\ViolationList;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ViolationListTest extends TestCase
{
    private ViolationList $violationList;

    public function setUp(): void
    {
        parent::setUp();

        $this->violationList = new ViolationList();
    }

    #[Test]
    public function it_is_empty_by_default(): void
    {
        self::assertTrue($this->violationList->hasNoError());
        self::assertCount(0, $this->violationList);
    }

    #[Test]
    public function it_can_be_instantiated_with_a_violation(): void
    {
        $violation = new Violation('This is a violation.');
        $list = new ViolationList([$violation]);

        self::assertCount(1, $list);
        self::assertFalse($list->hasNoError());
        self::assertTrue($list->hasErrors());
        self::assertSame($violation, $list[0]);
    }

    #[Test]
    public function it_can_add_a_violation(): void
    {
        $violation = new Violation('This is a violation.');
        $this->violationList->add(ErrorCode::FailedItemParsing, $violation);

        self::assertCount(1, $this->violationList);
        self::assertSame($violation, $this->violationList->get(ErrorCode::FailedItemParsing));
        self::assertSame($violation, $this->violationList[ErrorCode::FailedItemParsing]);
    }

    #[Test]
    public function it_can_add_multiple_violations(): void
    {
        $violation1 = new Violation('This is a violation 1.');
        $violation2 = new Violation('This is a violation 2.');
        $violation3 = new Violation('This is a violation 3.');

        $violations = [
            'error 1' => $violation1,
            'error 2' => $violation2,
            'error 3' => $violation3,
        ];

        $this->violationList->addAll($violations);

        self::assertCount(3, $this->violationList);
        self::assertTrue($this->violationList->hasErrors());
        self::assertFalse($this->violationList->has(ErrorCode::FailedItemParsing));
        self::assertSame($violation1, $this->violationList->get('error 1'));
        self::assertSame($violation2, $this->violationList['error 2']);
        self::assertSame([
            'error 1' => 'This is a violation 1.',
            'error 2' => 'This is a violation 2.',
            'error 3' => 'This is a violation 3.',
        ], $this->violationList->summary());

        self::assertSame(array_values($violations), iterator_to_array($this->violationList, false));
        $toString = 'This is a violation 1.'."\n".'This is a violation 2.'."\n".'This is a violation 3.';
        self::assertSame($toString, (string) $this->violationList);
        self::assertEquals(new Violation($toString), $this->violationList->toException());
    }
}
