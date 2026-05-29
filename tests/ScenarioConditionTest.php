<?php

declare(strict_types=1);

use Eleload\LoadTesting\ScenarioCondition;

// -----------------------------------------------------------------------
// ScenarioCondition::evaluate() – status field
// -----------------------------------------------------------------------

test('ScenarioCondition == evaluates true when status matches', function (): void {
    $cond = new ScenarioCondition('status', '==', 200);
    assertTrue($cond->evaluate(200, ''));
});

test('ScenarioCondition == evaluates false when status does not match', function (): void {
    $cond = new ScenarioCondition('status', '==', 200);
    assertFalse($cond->evaluate(404, ''));
});

test('ScenarioCondition != evaluates true when status differs', function (): void {
    $cond = new ScenarioCondition('status', '!=', 200);
    assertTrue($cond->evaluate(404, ''));
    assertFalse($cond->evaluate(200, ''));
});

test('ScenarioCondition < evaluates correctly for status', function (): void {
    $cond = new ScenarioCondition('status', '<', 300);
    assertTrue($cond->evaluate(200, ''));
    assertFalse($cond->evaluate(400, ''));
});

test('ScenarioCondition > evaluates correctly for status', function (): void {
    $cond = new ScenarioCondition('status', '>', 299);
    assertTrue($cond->evaluate(400, ''));
    assertFalse($cond->evaluate(200, ''));
});

// -----------------------------------------------------------------------
// ScenarioCondition::evaluate() – body field
// -----------------------------------------------------------------------

test('ScenarioCondition contains evaluates true when body contains value', function (): void {
    $cond = new ScenarioCondition('body', 'contains', 'success');
    assertTrue($cond->evaluate(200, '{"result":"success"}'));
    assertFalse($cond->evaluate(200, '{"result":"failure"}'));
});

test('ScenarioCondition regex_match evaluates true when body matches pattern', function (): void {
    $cond = new ScenarioCondition('body', 'regex_match', '"status":"ok"');
    assertTrue($cond->evaluate(200, '{"status":"ok","id":1}'));
    assertFalse($cond->evaluate(200, '{"status":"error"}'));
});

// -----------------------------------------------------------------------
// ScenarioCondition constructor validation
// -----------------------------------------------------------------------

test('ScenarioCondition throws for invalid field', function (): void {
    assertThrows(
        fn () => new ScenarioCondition('headers', '==', 200),
        InvalidArgumentException::class,
        'field'
    );
});

test('ScenarioCondition throws for invalid operator', function (): void {
    assertThrows(
        fn () => new ScenarioCondition('status', 'startswith', '2'),
        InvalidArgumentException::class,
        'operator'
    );
});
