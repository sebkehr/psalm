<?php

namespace Psalm\Tests;

use Psalm\Tests\Traits\InvalidCodeAnalysisTestTrait;
use Psalm\Tests\Traits\ValidCodeAnalysisTestTrait;

use const DIRECTORY_SEPARATOR;

class ArrayFunctionCallTest extends TestCase
{
    use InvalidCodeAnalysisTestTrait;
    use ValidCodeAnalysisTestTrait;

    /**
     * @return iterable<string,array{code:string,assertions?:array<string,string>,ignored_issues?:list<string>}>
     */
    public function providerValidCodeParse(): iterable
    {
        return [
            'arrayFilter' => [
                'code' => '<?php
                    $d = array_filter(["a" => rand(0, 10), "b" => rand(0, 10), "c" => null]);
                    $e = array_filter(
                        ["a" => rand(0, 10), "b" => rand(0, 10), "c" => null],
                        function(?int $i): bool {
                            return true;
                        }
                    );',
                'assertions' => [
                    '$d' => 'array{a?: int<1, 10>, b?: int<1, 10>}',
                    '$e' => 'array<string, int<0, 10>|null>',
                ],
            ],
            'positiveIntArrayFilter' => [
                'code' => '<?php
                    /**
                     * @param numeric $a
                     * @param positive-int $positiveOne
                     * @param int<0,12> $d
                     * @param int<1,12> $f
                     * @psalm-return array{a: numeric, b?: int, c: positive-int, d?: int<0, 12>, f: int<1,12>}
                     */
                    function makeAList($a, int $anyInt, int $positiveOne, int $d, int $f): array {
                        return array_filter(["a" => "1", "b" => $anyInt, "c" => $positiveOne, "d" => $d, "f" => $f]);
                    }'
            ],
            'arrayFilterAdvanced' => [
                'code' => '<?php
                    $f = array_filter(["a" => 5, "b" => 12, "c" => null], function(?int $val, string $key): bool {
                        return true;
                    }, ARRAY_FILTER_USE_BOTH);
                    $g = array_filter(["a" => 5, "b" => 12, "c" => null], function(string $val): bool {
                        return true;
                    }, ARRAY_FILTER_USE_KEY);

                    $bar = "bar";

                    $foo = [
                        $bar => function (): string {
                            return "baz";
                        },
                    ];

                    $foo = array_filter(
                        $foo,
                        function (string $key): bool {
                            return $key === "bar";
                        },
                        ARRAY_FILTER_USE_KEY
                    );',
                'assertions' => [
                    '$f' => 'array<string, int|null>',
                    '$g' => 'array<string, int|null>',
                ],
            ],
            'arrayFilterIgnoreNullable' => [
                'code' => '<?php
                    class A {
                        /**
                         * @return array<int, self|null>
                         */
                        public function getRows() : array {
                            return [new self, null];
                        }

                        public function filter() : void {
                            $arr = array_filter(
                                static::getRows(),
                                function (self $row) : bool {
                                    return is_a($row, static::class);
                                }
                            );
                        }
                    }',
                'assertions' => [],
                'ignored_issues' => ['PossiblyInvalidArgument'],
            ],
            'arrayFilterAllowTrim' => [
                'code' => '<?php
                    $foo = array_filter(["hello ", " "], "trim");',
            ],
            'arrayFilterAllowNull' => [
                'code' => '<?php
                    function foo() : array {
                        return array_filter(
                            array_map(
                                /** @return null */
                                function (int $arg) {
                                    return null;
                                },
                                [1, 2, 3]
                            )
                        );
                    }',
            ],
            'arrayFilterNamedFunction' => [
                'code' => '<?php
                    /**
                     * @param array<int, DateTimeImmutable|null> $a
                     * @return array<int, DateTimeImmutable>
                     */
                    function foo(array $a) : array {
                        return array_filter($a, "is_object");
                    }',
            ],
            'arrayFilterFleshOutType' => [
                'code' => '<?php
                    class Baz {
                        public const STATUS_FOO = "foo";
                        public const STATUS_BAR = "bar";
                        public const STATUS_QUX = "qux";

                        /**
                         * @psalm-param self::STATUS_* $role
                         */
                        public static function isStatus(string $role): bool
                        {
                            return !\in_array($role, [self::STATUS_BAR, self::STATUS_QUX], true);
                        }
                    }

                    /** @psalm-var array<Baz::STATUS_*> $statusList */
                    $statusList = [Baz::STATUS_FOO, Baz::STATUS_QUX];
                    $statusList = array_filter($statusList, [Baz::class, "isStatus"]);'
            ],
            'arrayKeysNonEmpty' => [
                'code' => '<?php
                    $a = array_keys(["a" => 1, "b" => 2]);',
                'assertions' => [
                    '$a' => 'non-empty-list<string>',
                ],
            ],
            'arrayKeysMixed' => [
                'code' => '<?php
                    /** @var array */
                    $b = ["a" => 5];
                    $a = array_keys($b);',
                'assertions' => [
                    '$a' => 'list<array-key>',
                ],
                'ignored_issues' => ['MixedArgument'],
            ],
            'arrayValues' => [
                'code' => '<?php
                    $b = array_values(["a" => 1, "b" => 2]);
                    $c = array_values(["a" => "hello", "b" => "jello"]);',
                'assertions' => [
                    '$b' => 'non-empty-list<int>',
                    '$c' => 'non-empty-list<string>',
                ],
            ],
            'arrayCombine' => [
                'code' => '<?php
                    $c = array_combine(["a", "b", "c"], [1, 2, 3]);',
                'assertions' => [
                    '$c' => 'false|non-empty-array<string, int>',
                ],
                'ignored_issues' => [],
                'php_version' => '7.4',
            ],
            'arrayCombinePHP8' => [
                'code' => '<?php
                    $c = array_combine(["a", "b"], [1, 2, 3]);',
                'assertions' => [
                    '$c' => 'non-empty-array<string, int>',
                ],
                'ignored_issues' => [],
                'php_version' => '8.0',
            ],
            'arrayCombineNotMatching' => [
                'code' => '<?php
                    $c = array_combine(["a", "b"], [1, 2, 3]);',
                'assertions' => [
                    '$c' => 'false|non-empty-array<string, int>',
                ],
                'ignored_issues' => [],
                'php_version' => '7.4',
            ],
            'arrayCombineDynamicParams' => [
                'code' => '<?php
                    /** @return array<string> */
                    function getStrings(): array{ return []; }
                    /** @return array<int> */
                    function getInts(): array{ return []; }
                    $c = array_combine(getStrings(), getInts());',
                'assertions' => [
                    '$c' => 'array<string, int>|false',
                ],
            ],
            'arrayMergeIntArrays' => [
                'code' => '<?php
                    $d = array_merge(["a", "b", "c", "d"], [1, 2, 3]);',
                'assertions' => [
                    '$d' => 'array{0: string, 1: string, 2: string, 3: string, 4: int, 5: int, 6: int}',
                ],
            ],
            'arrayMergePossiblyUndefined' => [
                'code' => '<?php
                    /**
                     * @param array{host?:string} $opts
                     * @return array{host:string|int}
                     */
                    function b(array $opts): array {
                        return array_merge(["host" => 5], $opts);
                    }',
            ],
            'arrayMergeListResultWithArray' => [
                'code' => '<?php
                    /**
                     * @param array<int, string> $list
                     * @return list<string>
                     */
                    function bar(array $list) : array {
                        return array_merge($list, ["test"]);
                    }',
            ],
            'arrayMergeListResultWithList' => [
                'code' => '<?php
                    /**
                     * @param list<string> $list
                     * @return list<string>
                     */
                    function foo(array $list) : array {
                        return array_merge($list, ["test"]);
                    }',
            ],
            'arrayMergeTypes' => [
                'code' => '<?php
                    /**
                     * @psalm-type A=array{name: string}
                     * @psalm-type B=array{age: int}
                     */
                    class Demo
                    {
                        /**
                         * @param A $a
                         * @param B $b
                         * @return A&B
                         */
                        public function merge($a, $b): array
                        {
                            return array_merge($a, $b);
                        }
                    }',
            ],
            'arrayReplaceIntArrays' => [
                'code' => '<?php
                    $d = array_replace(["a", "b", "c", "d"], [1, 2, 3]);',
                'assertions' => [
                    '$d' => 'array{0: int, 1: int, 2: int, 3: string}',
                ],
            ],
            'arrayReplacePossiblyUndefined' => [
                'code' => '<?php
                    /**
                     * @param array{host?:string} $opts
                     * @return array{host:string|int}
                     */
                    function b(array $opts): array {
                        return array_replace(["host" => 5], $opts);
                    }',
            ],
            'arrayReplaceListResultWithArray' => [
                'code' => '<?php
                    /**
                     * @param array<int, string> $list
                     * @return list<string>
                     */
                    function bar(array $list) : array {
                        return array_replace($list, ["test"]);
                    }',
            ],
            'arrayReplaceListResultWithList' => [
                'code' => '<?php
                    /**
                     * @param list<string> $list
                     * @return list<string>
                     */
                    function foo(array $list) : array {
                        return array_replace($list, ["test"]);
                    }',
            ],
            'arrayReplaceTypes' => [
                'code' => '<?php
                    /**
                     * @psalm-type A=array{name: string}
                     * @psalm-type B=array{age: int}
                     */
                    class Demo
                    {
                        /**
                         * @param A $a
                         * @param B $b
                         * @return A&B
                         */
                        public function replace($a, $b): array
                        {
                            return array_replace($a, $b);
                        }
                    }',
            ],
            'arrayReverseDontPreserveKey' => [
                'code' => '<?php
                    $d = array_reverse(["a", "b", 1, "d" => 4]);',
                'assertions' => [
                    '$d' => 'non-empty-array<int|string, int|string>',
                ],
            ],
            'arrayReverseDontPreserveKeyExplicitArg' => [
                'code' => '<?php
                    $d = array_reverse(["a", "b", 1, "d" => 4], false);',
                'assertions' => [
                    '$d' => 'non-empty-array<int|string, int|string>',
                ],
            ],
            'arrayReversePreserveKey' => [
                'code' => '<?php
                    $d = array_reverse(["a", "b", 1], true);',
                'assertions' => [
                    '$d' => 'non-empty-array<int, int|string>',
                ],
            ],
            'arrayDiff' => [
                'code' => '<?php
                    $d = array_diff(["a" => 5, "b" => 12], [5]);',
                'assertions' => [
                    '$d' => 'array<string, int>',
                ],
            ],
            'arrayDiffIsVariadic' => [
                'code' => '<?php
                    array_diff([], [], [], [], []);',
                'assertions' => [],
            ],
            'arrayDiffKeyIsVariadic' => [
                'code' => '<?php
                    array_diff_key([], [], [], [], []);',
                'assertions' => [],
            ],
            'arrayDiffAssoc' => [
                'code' => '<?php
                    /**
                     * @var array<string, int> $a
                     * @var array $b
                     * @var array $c
                     */
                    $r = array_diff_assoc($a, $b, $c);',
                'assertions' => [
                    '$r' => 'array<string, int>',
                ],
            ],
            'arrayPopMixed' => [
                'code' => '<?php
                    /** @var mixed */
                    $b = ["a" => 5, "c" => 6];
                    $a = array_pop($b);',
                'assertions' => [
                    '$a' => 'mixed',
                    '$b' => 'mixed',
                ],
                'ignored_issues' => ['MixedAssignment', 'MixedArgument'],
            ],
            'arrayPopNonEmpty' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if ($a) {
                        $b = array_pop($a);
                    }
                    $c = array_pop($a);',
                'assertions' => [
                    '$b' => 'int',
                    '$c' => 'int|null',
                ],
            ],
            'arrayPopNonEmptyAfterIsset' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (isset($a["a"])) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCount' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (count($a)) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayShiftNonEmptyList' => [
                'code' => '<?php
                    /** @param non-empty-list $arr */
                    function type_of_array_shift(array $arr) : int {
                        if (\is_int($arr[0])) {
                            return \array_shift($arr);
                        }

                        return 0;
                    }',
            ],
            'arrayShiftFunkyTKeyedArrayList' => [
                'code' => '<?php
                    /**
                     * @param non-empty-list<string>|array{null} $arr
                     * @return array<int, string>
                     */
                    function foo(array $arr) {
                        array_shift($arr);
                        return $arr;
                    }'
            ],
            'arrayPopNonEmptyAfterCountEqualsOne' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (count($a) === 1) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountSoftEqualsOne' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (count($a) == 1) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountGreaterThanOne' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (count($a) > 0) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountGreaterOrEqualsOne' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (count($a) >= 1) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountEqualsOneReversed' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (1 === count($a)) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountSoftEqualsOneReversed' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (1 == count($a)) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountGreaterThanOneReversed' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (0 < count($a)) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterCountGreatorOrEqualToOneReversed' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $b = 5;
                    if (1 <= count($a)) {
                        $b = array_pop($a);
                    }',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayNotEmptyArrayAfterCountLessThanEqualToOne' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    if (count($leftCount) <= 1) {
                        echo $leftCount[0];
                    }
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    if (1 >= count($rightCount)) {
                        echo $rightCount[0];
                    }',
            ],
            'arrayNotEmptyArrayAfterCountLessThanTwo' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    if (count($leftCount) < 2) {
                        echo $leftCount[0];
                    }
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    if (2 > count($rightCount)) {
                        echo $rightCount[0];
                    }',
            ],
            'arrayEmptyArrayAfterCountLessThanOne' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    assert (count($leftCount) < 1);
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    assert (1 > count($rightCount));',
                'assertions' => [
                    '$leftCount' => 'array<never, never>',
                    '$rightCount' => 'array<never, never>',
                ],
            ],
            'arrayEmptyArrayAfterCountLessThanEqualToZero' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    assert (count($leftCount) <= 0);
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    assert (0 >= count($rightCount));',
                'assertions' => [
                    '$leftCount' => 'array<never, never>',
                    '$rightCount' => 'array<never, never>',
                ],
            ],
            'arrayNotNonEmptyArrayAfterCountGreaterThanEqualToZero' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    assert(count($leftCount) >= 0);
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    assert(0 <= count($rightCount));',
                'assertions' => [
                    '$leftCount' => 'list<int>',
                    '$rightCount' => 'list<int>',
                ],
            ],
            'arrayNotNonEmptyArrayAfterCountGreaterThanMinusOne' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    assert (count($leftCount) > -1);
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    assert (-1 < count($rightCount));',
                'assertions' => [
                    '$leftCount' => 'list<int>',
                    '$rightCount' => 'list<int>',
                ],
            ],
            'arrayNonEmptyArrayAfterCountGreaterThanEqualToOne' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    assert(count($leftCount) >= 1);
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    assert(1 <= count($rightCount));',
                'assertions' => [
                    '$leftCount' => 'non-empty-list<int>',
                    '$rightCount' => 'non-empty-list<int>',
                ],
            ],
            'arrayNonEmptyArrayAfterCountGreaterThanZero' => [
                'code' => '<?php
                    /** @var list<int> */
                    $leftCount = [1, 2, 3];
                    assert (count($leftCount) > 0);
                    /** @var list<int> */
                    $rightCount = [1, 2, 3];
                    assert (0 < count($rightCount));',
                'assertions' => [
                    '$leftCount' => 'non-empty-list<int>',
                    '$rightCount' => 'non-empty-list<int>',
                ],
            ],
            'arrayPopNonEmptyAfterArrayAddition' => [
                'code' => '<?php
                    /** @var array<string, int> */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $a["foo"] = 10;
                    $b = array_pop($a);',
                'assertions' => [
                    '$b' => 'int',
                ],
            ],
            'arrayPopNonEmptyAfterMixedArrayAddition' => [
                'code' => '<?php
                    /** @var array */
                    $a = ["a" => 5, "b" => 6, "c" => 7];
                    $a[] = "hello";
                    $b = array_pop($a);',
                'assertions' => [
                    '$b' => 'mixed|string',
                ],
                'ignored_issues' => [
                    'MixedAssignment',
                ],
            ],
            'uasort' => [
                'code' => '<?php
                    function foo (int $a, int $b): int {
                        return $a > $b ? 1 : -1;
                    }
                    $manifest = ["a" => 1, "b" => 2];
                    uasort(
                        $manifest,
                        "foo"
                    );
                    $emptyManifest = [];
                    uasort(
                        $emptyManifest,
                        "foo"
                    );
                    ',
                'assertions' => [
                    '$manifest' => 'non-empty-array<string, int>',
                    '$emptyManifest' => 'array<never, never>',
                ],
            ],
            'uksort' => [
                'code' => '<?php
                    function foo (string $a, string $b): int {
                        return $a <=> $b;
                    }

                    $array = ["b" => 1, "a" => 2];
                    uksort(
                        $array,
                        "foo"
                    );
                    $emptyArray = [];
                    uksort(
                        $emptyArray,
                        "foo"
                    );',
                'assertions' => [
                    '$array' => 'non-empty-array<string, int>',
                    '$emptyArray' => 'array<never, never>',
                ],
            ],
            'arrayMergeTKeyedArray' => [
                'code' => '<?php
                  /**
                   * @param array<string, int> $a
                   * @return array<string, int>
                   */
                  function foo($a)
                  {
                    return $a;
                  }

                  $a1 = ["hi" => 3];
                  $a2 = ["bye" => 5];
                  $a3 = array_merge($a1, $a2);

                  foo($a3);',
                'assertions' => [
                    '$a3' => 'array{hi: int, bye: int}',
                ],
            ],
            'arrayReplaceTKeyedArray' => [
                'code' => '<?php
                  /**
                   * @param array<string, int> $a
                   * @return array<string, int>
                   */
                  function foo($a)
                  {
                    return $a;
                  }

                  $a1 = ["hi" => 3];
                  $a2 = ["bye" => 5];
                  $a3 = array_replace($a1, $a2);

                  foo($a3);',
                'assertions' => [
                    '$a3' => 'array{hi: int, bye: int}',
                ],
            ],
            'arrayRand' => [
                'code' => '<?php
                    $vars = ["x" => "a", "y" => "b"];
                    $c = array_rand($vars);
                    $d = $vars[$c];
                    $more_vars = ["a", "b"];
                    $e = array_rand($more_vars);',

                'assertions' => [
                    '$vars' => 'array{x: string, y: string}',
                    '$c' => 'string',
                    '$d' => 'string',
                    '$more_vars' => 'array{string, string}',
                    '$e' => 'int',
                ],
            ],
            'arrayRandMultiple' => [
                'code' => '<?php
                    $vars = ["x" => "a", "y" => "b"];
                    $b = 3;
                    $c = array_rand($vars, 1);
                    $d = array_rand($vars, 2);
                    $e = array_rand($vars, 3);
                    $f = array_rand($vars, $b);',

                'assertions' => [
                    '$vars' => 'array{x: string, y: string}',
                    '$c' => 'string',
                    '$e' => 'list<string>',
                    '$f' => 'list<string>|string',
                ],
            ],
            'arrayKeysNoEmpty' => [
                'code' => '<?php
                    function expect_string(string $x): void {
                        echo $x;
                    }

                    function test(): void {
                        foreach (array_keys([]) as $key) {
                            expect_string($key);
                        }
                    }',
                'assertions' => [],
                'ignored_issues' => ['MixedAssignment', 'MixedArgument', 'MixedArgumentTypeCoercion', 'NoValue'],
            ],
            'arrayPopNotNullable' => [
                'code' => '<?php
                    function expectsInt(int $a) : void {}

                    /**
                     * @param array<array-key, array{item:int}> $list
                     */
                    function test(array $list) : void
                    {
                        while (!empty($list)) {
                            $tmp = array_pop($list);
                            expectsInt($tmp["item"]);
                        }
                    }',
            ],
            'arrayFilterWithAssert' => [
                'code' => '<?php
                    $a = array_filter(
                        [1, "hello", 6, "goodbye"],
                        function ($s): bool {
                            return is_string($s);
                        }
                    );',
                'assertions' => [
                    '$a' => 'array<int, string>',
                ],
                'ignored_issues' => [
                    'MissingClosureParamType',
                ],
            ],
            'arrayFilterUseKey' => [
                'code' => '<?php
                    $bar = "bar";

                    $foo = [
                        $bar => function (): string {
                            return "baz";
                        },
                    ];

                    $foo = array_filter(
                        $foo,
                        function (string $key): bool {
                            return $key === "bar";
                        },
                        ARRAY_FILTER_USE_KEY
                    );',
                'assertions' => [
                    '$foo' => 'array<string, pure-Closure():string>',
                ],
            ],
            'ignoreFalsableCurrent' => [
                'code' => '<?php
                    /** @param string[] $arr */
                    function foo(array $arr): string {
                        return current($arr);
                    }
                    /** @param string[] $arr */
                    function bar(array $arr): string {
                        $a = current($arr);
                        if ($a === false) {
                            return "hello";
                        }
                        return $a;
                    }
                    /**
                     * @param string[] $arr
                     * @return false|string
                     */
                    function bat(array $arr) {
                        return current($arr);
                    }',
            ],
            'arraySumEmpty' => [
                'code' => '<?php
                    $foo = array_sum([]) + 1;',
                'assertions' => [
                    '$foo' => 'int',
                ],
            ],
            'arraySumOnlyInt' => [
                'code' => '<?php
                    $foo = array_sum([5,18]);',
                'assertions' => [
                    '$foo' => 'int',
                ],
            ],
            'arraySumOnlyFloat' => [
                'code' => '<?php
                    $foo = array_sum([5.1,18.2]);',
                'assertions' => [
                    '$foo' => 'float',
                ],
            ],
            'arraySumNumeric' => [
                'code' => '<?php
                    $foo = array_sum(["5","18"]);',
                'assertions' => [
                    '$foo' => 'float|int',
                ],
            ],
            'arraySumMix' => [
                'code' => '<?php
                    $foo = array_sum([5,18.5]);',
                'assertions' => [
                    '$foo' => 'float',
                ],
            ],
            'arrayMapWithArrayAndCallable' => [
                'code' => '<?php
                    /**
                     * @psalm-return array<array-key, int>
                     */
                    function foo(array $v): array {
                        $r = array_map("intval", $v);
                        return $r;
                    }',
            ],
            'arrayMapTKeyedArrayAndCallable' => [
                'code' => '<?php
                    /**
                     * @psalm-return array{key1:int,key2:int}
                     */
                    function foo(): array {
                        $v = ["key1"=> 1, "key2"=> "2"];
                        $r = array_map("intval", $v);
                        return $r;
                    }',
            ],
            'arrayMapTKeyedArrayListAndCallable' => [
                'code' => '<?php
                    /** @param list<int> $list */
                    function takesList(array $list): void {}

                    takesList(
                        array_map(
                            "intval",
                            ["1", "2", "3"]
                        )
                    );',
            ],
            'arrayMapTKeyedArrayAndClosure' => [
                'code' => '<?php
                    /**
                     * @psalm-return array{key1:int,key2:int}
                     */
                    function foo(): array {
                      $v = ["key1"=> 1, "key2"=> "2"];
                      $r = array_map(function($i) : int { return intval($i);}, $v);
                      return $r;
                    }',
                'assertions' => [],
                'ignored_issues' => [
                    'MissingClosureParamType'
                ],
            ],
            'arrayMapTKeyedArrayListAndClosure' => [
                'code' => '<?php
                    /** @param list<string> $list */
                    function takesList(array $list): void {}

                    takesList(
                        array_map(
                            function (string $str): string { return $str . "x"; },
                            ["foo", "bar", "baz"]
                        )
                    );',
            ],
            'arrayMapUntypedCallable' => [
                'code' => '<?php
                    /**
                     * @var callable $callable
                     * @var array<string, int> $array
                     */
                    $a = array_map($callable, $array);

                    /**
                     * @var callable $callable
                     * @var array<string, int> $array
                     */
                    $b = array_map($callable, $array, $array);

                    /**
                     * @var callable $callable
                     * @var list<string> $list
                     */
                    $c = array_map($callable, $list);

                    /**
                     * @var callable $callable
                     * @var list<string> $list
                     */
                    $d = array_map($callable, $list, $list);',
                'assertions' => [
                    '$a' => 'array<string, mixed>',
                    '$b' => 'list<mixed>',
                    '$c' => 'list<mixed>',
                    '$d' => 'list<mixed>',
                ],
            ],
            'arrayFilterGoodArgs' => [
                'code' => '<?php
                    function fooFoo(int $i) : bool {
                      return true;
                    }

                    class A {
                        public static function barBar(int $i) : bool {
                            return true;
                        }
                    }

                    array_filter([1, 2, 3], "fooFoo");
                    array_filter([1, 2, 3], "foofoo");
                    array_filter([1, 2, 3], "FOOFOO");
                    array_filter([1, 2, 3], "A::barBar");
                    array_filter([1, 2, 3], "A::BARBAR");
                    array_filter([1, 2, 3], "A::barbar");',
            ],
            'arrayFilterIgnoreMissingClass' => [
                'code' => '<?php
                    array_filter([1, 2, 3], "A::bar");',
                'assertions' => [],
                'ignored_issues' => ['UndefinedClass'],
            ],
            'arrayFilterIgnoreMissingMethod' => [
                'code' => '<?php
                    class A {
                        public static function bar(int $i) : bool {
                            return true;
                        }
                    }

                    array_filter([1, 2, 3], "A::foo");',
                'assertions' => [],
                'ignored_issues' => ['UndefinedMethod'],
            ],
            'arrayMapParamDefault' => [
                'code' => '<?php
                    $arr = ["a", "b"];
                    array_map("mapdef", $arr, array_fill(0, count($arr), 1));
                    function mapdef(string $_a, int $_b = 0): string {
                        return "a";
                    }',
            ],
            'arrayFillZeroLength' => [
                'code' => '<?php
                    count(array_fill(0, 0, 0)) === 0;',
            ],
            'implodeMultiDimensionalArray' => [
                'code' => '<?php
                    $urls = array_map("implode", [["a", "b"]]);',
            ],
            'implodeNonEmptyArrayAndString' => [
                'code' => '<?php
                    $l = ["a", "b"];
                    $k = [1, 2, 3];
                    $a = implode(":", $l);
                    $b = implode(":", $k);',
                'assertions' => [
                    '$a===' => 'non-empty-literal-string',
                    '$b===' => 'non-empty-literal-string',
                ]
            ],
            'implodeArrayOfNonEmptyStringAndEmptyString' => [
                'code' => '<?php
                    class Foo {
                        const DIR = __DIR__;
                    }
                    $l = ["a", "b"];
                    $k = [Foo::DIR];
                    $a = implode("", $l);
                    $b = implode("", $k);',
                'assertions' => [
                    '$a===' => 'non-empty-literal-string',
                    '$b===' => 'non-empty-string',
                ]
            ],
            'implodeEmptyArrayAndString' => [
                'code' => '<?php
                    $l = [""];
                    $k = [];
                    $a = implode("", $l);
                    $b = implode("", $k);',
                'assertions' => [
                    '$a===' => 'string',
                    '$b===' => 'string',
                ]
            ],
            'key' => [
                'code' => '<?php
                    $a = ["one" => 1, "two" => 3];
                    $b = key($a);',
                'assertions' => [
                    '$b' => 'null|string',
                ],
            ],
            'keyEmptyArray' => [
                'code' => '<?php
                    $a = [];
                    $b = key($a);',
                'assertions' => [
                    '$b' => 'null',
                ],
            ],
            'keyNonEmptyArray' => [
                'code' => '<?php
                    /**
                     * @param non-empty-array $arr
                     * @return null|array-key
                     */
                    function foo(array $arr) {
                        return key($arr);
                    }',
            ],
            'arrayKeyFirst' => [
                'code' => '<?php
                    /** @return array<string, int> */
                    function makeArray(): array { return ["one" => 1, "two" => 3]; }
                    $a = makeArray();
                    $b = array_key_first($a);
                    $c = null;
                    if ($b !== null) {
                        $c = $a[$b];
                    }',
                'assertions' => [
                    '$b' => 'null|string',
                    '$c' => 'int|null',
                ],
            ],
            'arrayKeyFirstNonEmpty' => [
                'code' => '<?php
                    $a = ["one" => 1, "two" => 3];
                    $b = array_key_first($a);
                    $c = $a[$b];',
                'assertions' => [
                    '$b' => 'string',
                    '$c' => 'int',
                ],
            ],
            'arrayKeyFirstEmpty' => [
                'code' => '<?php
                    $a = [];
                    $b = array_key_first($a);',
                'assertions' => [
                    '$b' => 'null'
                ],
            ],
            'arrayKeyLast' => [
                'code' => '<?php
                    /** @return array<string, int> */
                    function makeArray(): array { return ["one" => 1, "two" => 3]; }
                    $a = makeArray();
                    $b = array_key_last($a);
                    $c = null;
                    if ($b !== null) {
                        $c = $a[$b];
                    }',
                'assertions' => [
                    '$b' => 'null|string',
                    '$c' => 'int|null',
                ],
            ],
            'arrayKeyLastNonEmpty' => [
                'code' => '<?php
                    $a = ["one" => 1, "two" => 3];
                    $b = array_key_last($a);
                    $c = $a[$b];',
                'assertions' => [
                    '$b' => 'string',
                    '$c' => 'int',
                ],
            ],
            'arrayKeyLastEmpty' => [
                'code' => '<?php
                    $a = [];
                    $b = array_key_last($a);',
                'assertions' => [
                    '$b' => 'null'
                ],
            ],
            'arrayResetNonEmptyArray' => [
                'code' => '<?php
                    /** @return non-empty-array<string, int> */
                    function makeArray(): array { return ["one" => 1, "two" => 3]; }
                    $a = makeArray();
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'int'
                ],
            ],
            'arrayResetNonEmptyList' => [
                'code' => '<?php
                    /** @return non-empty-list<int> */
                    function makeArray(): array { return [1, 3]; }
                    $a = makeArray();
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'int'
                ],
            ],
            'arrayResetNonEmptyTKeyedArray' => [
                'code' => '<?php
                    $a = ["one" => 1, "two" => 3];
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'int'
                ],
            ],
            'arrayResetEmptyArray' => [
                'code' => '<?php
                    $a = [];
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'false'
                ],
            ],
            'arrayResetEmptyList' => [
                'code' => '<?php
                    /** @return list<never> */
                    function makeArray(): array { return []; }
                    $a = makeArray();
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'false'
                ],
            ],
            'arrayResetMaybeEmptyArray' => [
                'code' => '<?php
                    /** @return array<string, int> */
                    function makeArray(): array { return ["one" => 1, "two" => 3]; }
                    $a = makeArray();
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'false|int'
                ],
            ],
            'arrayResetMaybeEmptyList' => [
                'code' => '<?php
                    /** @return list<int> */
                    function makeArray(): array { return []; }
                    $a = makeArray();
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'false|int'
                ],
            ],
            'arrayResetMaybeEmptyTKeyedArray' => [
                'code' => '<?php
                    /** @return array{foo?: int} */
                    function makeArray(): array { return []; }
                    $a = makeArray();
                    $b = reset($a);',
                'assertions' => [
                    '$b' => 'false|int'
                ],
            ],
            'arrayEndNonEmptyArray' => [
                'code' => '<?php
                    /** @return non-empty-array<string, int> */
                    function makeArray(): array { return ["one" => 1, "two" => 3]; }
                    $a = makeArray();
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'int'
                ],
            ],
            'arrayEndNonEmptyList' => [
                'code' => '<?php
                    /** @return non-empty-list<int> */
                    function makeArray(): array { return [1, 3]; }
                    $a = makeArray();
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'int'
                ],
            ],
            'arrayEndNonEmptyTKeyedArray' => [
                'code' => '<?php
                    $a = ["one" => 1, "two" => 3];
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'int'
                ],
            ],
            'arrayEndEmptyArray' => [
                'code' => '<?php
                    $a = [];
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'false'
                ],
            ],
            'arrayEndEmptyList' => [
                'code' => '<?php
                    /** @return list<never> */
                    function makeArray(): array { return []; }
                    $a = makeArray();
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'false'
                ],
            ],
            'arrayEndMaybeEmptyArray' => [
                'code' => '<?php
                    /** @return array<string, int> */
                    function makeArray(): array { return ["one" => 1, "two" => 3]; }
                    $a = makeArray();
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'false|int'
                ],
            ],
            'arrayEndMaybeEmptyList' => [
                'code' => '<?php
                    /** @return list<int> */
                    function makeArray(): array { return []; }
                    $a = makeArray();
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'false|int'
                ],
            ],
            'arrayEndMaybeEmptyTKeyedArray' => [
                'code' => '<?php
                    /** @return array{foo?: int} */
                    function makeArray(): array { return []; }
                    $a = makeArray();
                    $b = end($a);',
                'assertions' => [
                    '$b' => 'false|int'
                ],
            ],
            'arrayColumnInference' => [
                'code' => '<?php
                    function makeMixedArray(): array { return []; }
                    /** @return array<array<int,bool>> */
                    function makeGenericArray(): array { return []; }
                    /** @return array<array{0:string}> */
                    function makeShapeArray(): array { return []; }
                    /** @return array<array{0:string}|int> */
                    function makeUnionArray(): array { return []; }
                    /** @return array<string, array{x?:int, y?:int, width?:int, height?:int}> */
                    function makeKeyedArray(): array { return []; }
                    $a = array_column([[1], [2], [3]], 0);
                    $b = array_column([["a" => 1], ["a" => 2], ["a" => 3]], "a");
                    $c = array_column([["a" => 1], ["a" => 2], ["a" => 3]], null, "a");
                    $d = array_column([["a" => 1], ["a" => 2], ["a" => 3]], null, "b");
                    $e = array_column([["a" => 1], ["a" => 2], ["a" => 3]], rand(0,1) ? "a" : "b", "b");
                    $f = array_column([["k" => "a", "v" => 1], ["k" => "b", "v" => 2]], "v", "k");
                    $g = array_column([], 0);
                    $h = array_column(makeMixedArray(), 0);
                    $i = array_column(makeMixedArray(), 0, "k");
                    $j = array_column(makeMixedArray(), 0, null);
                    $k = array_column(makeGenericArray(), 0);
                    $l = array_column(makeShapeArray(), 0);
                    $m = array_column(makeUnionArray(), 0);
                    $n = array_column([[0 => "test"]], 0);
                    $o = array_column(makeKeyedArray(), "y");
                    $p_prepare = makeKeyedArray();
                    assert($p_prepare !== []);
                    $p = array_column($p_prepare, "y");
                ',
                'assertions' => [
                    '$a' => 'non-empty-list<int>',
                    '$b' => 'non-empty-list<int>',
                    '$c' => 'array<int, array{a: int}>',
                    '$d' => 'array<array-key, array{a: int}>',
                    '$e' => 'array<array-key, mixed>',
                    '$f' => 'non-empty-array<string, int>',
                    '$g' => 'list<mixed>',
                    '$h' => 'list<mixed>',
                    '$i' => 'array<array-key, mixed>',
                    '$j' => 'list<mixed>',
                    '$k' => 'list<mixed>',
                    '$l' => 'list<string>',
                    '$m' => 'list<mixed>',
                    '$n' => 'non-empty-list<string>',
                    '$o' => 'list<int>',
                    '$p' => 'list<int>',
                ],
            ],
            'splatArrayIntersect' => [
                'code' => '<?php
                    $foo = [
                        [1, 2, 3],
                        [1, 2],
                    ];

                    $bar = array_intersect(... $foo);',
                'assertions' => [
                    '$bar' => 'array<int, int>',
                ],
            ],
            'arrayIntersectIsVariadic' => [
                'code' => '<?php
                    array_intersect([], [], [], [], []);',
                'assertions' => [],
            ],
            'arrayIntersectKeyIsVariadic' => [
                'code' => '<?php
                    array_intersect_key([], [], [], [], []);',
                'assertions' => [],
            ],
            'arrayIntersectKeyNoReturnType' => [
                'code' => '<?php
                    /**
                     * @psalm-suppress MissingReturnType
                     */
                    function unknown() {
                        return ["x" => "hello"];
                    }

                    class C {
                        /**
                         * @psalm-suppress MissingReturnType
                         */
                        public static function unknownStatic() {
                            return ["x" => "hello"];
                        }

                        /**
                         * @psalm-suppress MissingReturnType
                         */
                        public static function unknownInstance() {
                            return ["x" => "hello"];
                        }
                    }

                    /**
                     * @psalm-suppress MixedArgument
                     */
                    function sdn(array $s) : void {
                        $r = array_intersect_key(unknown(), array_filter($s));
                        if (empty($r)) {}

                        $r = array_intersect_key(C::unknownStatic(), array_filter($s));
                        if (empty($r)) {}

                        $r = array_intersect_key((new C)->unknownInstance(), array_filter($s));
                        if (empty($r)) {}
                    }',
            ],
            'arrayIntersectAssoc' => [
                'code' => '<?php
                    /**
                     * @var array<string, int> $a
                     * @var array $b
                     * @var array $c
                     */
                    $r = array_intersect_assoc($a, $b, $c);',
                'assertions' => [
                    '$r' => 'array<string, int>',
                ],
            ],
            'arrayReduce' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    function multiply (int $carry, int $item) : int {
                        return $carry * $item;
                    }

                    $f2 = function (int $carry, int $item) : int {
                        return $carry * $item;
                    };

                    $direct_closure_result = array_reduce(
                        $arr,
                        function (int $carry, int $item) : int {
                            return $carry * $item;
                        },
                        1
                    );

                    $passed_closure_result = array_reduce(
                        $arr,
                        $f2,
                        1
                    );

                    $function_call_result = array_reduce(
                        $arr,
                        "multiply",
                        1
                    );',
                'assertions' => [
                    '$direct_closure_result' => 'int',
                    '$passed_closure_result' => 'int',
                    '$function_call_result' => 'int',
                ],
            ],
            'arrayReduceStaticMethods' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    class C {
                        public static function multiply (int $carry, int $item) : int {
                            return $carry * $item;
                        }

                        public static function multiplySelf(array $arr): int {
                            return array_reduce($arr, [self::class, "multiply"], 1);
                        }

                        public static function multiplyStatic(array $arr): int {
                            return array_reduce($arr, [static::class, "multiply"], 1);
                        }
                    }

                    $self_call_result = C::multiplySelf($arr);
                    $static_call_result = C::multiplyStatic($arr);',
                'assertions' => [],
            ],
            'arrayReduceMixedReturn' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    $direct_closure_result = array_reduce(
                        $arr,
                        function (int $carry, int $item) {
                            return $_GET["boo"];
                        },
                        1
                    );',
                'assertions' => [],
                'ignored_issues' => ['MissingClosureReturnType', 'MixedAssignment'],
            ],
            'arraySpliceArray' => [
                'code' => '<?php
                    $a = [1, 2, 3];
                    $c = $a;
                    $b = ["a", "b", "c"];
                    array_splice($a, rand(-10, 0), rand(0, 10), $b);',
                'assertions' => [
                    '$a' => 'non-empty-list<int|string>',
                    '$b' => 'array{string, string, string}',
                    '$c' => 'array{int, int, int}',
                ],
            ],
            'arraySpliceReturn' => [
                'code' => '<?php
                    $d = [1, 2, 3];
                    $e = array_splice($d, -1, 1);',
                'assertions' => [
                    '$e' => 'list<int>'
                ],
            ],
            'arraySpliceOtherType' => [
                'code' => '<?php
                    $d = [["red"], ["green"], ["blue"]];
                    array_splice($d, -1, 1, "foo");',
                'assertions' => [
                    '$d' => 'array<int, array{string}|string>',
                ],
            ],
            'ksortPreserveShape' => [
                'code' => '<?php
                    $a = ["a" => 3, "b" => 4];
                    ksort($a);
                    acceptsAShape($a);

                    /**
                     * @param array{a:int,b:int} $a
                     */
                    function acceptsAShape(array $a): void {}',
            ],
            'arraySlicePreserveKeys' => [
                'code' => '<?php
                    $a = ["a" => 1, "b" => 2, "c" => 3];
                    $b = array_slice($a, 1, 2, true);
                    $c = array_slice($a, 1, 2, false);
                    $d = array_slice($a, 1, 2);',
                'assertions' => [
                    '$b' => 'array<string, int>',
                    '$c' => 'array<string, int>',
                    '$d' => 'array<string, int>',
                ],
            ],
            'arraySliceDontPreserveIntKeys' => [
                'code' => '<?php
                    $a = [1 => "a", 4 => "b", 3 => "c"];
                    $b = array_slice($a, 1, 2, true);
                    $c = array_slice($a, 1, 2, false);
                    $d = array_slice($a, 1, 2);',
                'assertions' => [
                    '$b' => 'array<int, string>',
                    '$c' => 'list<string>',
                    '$d' => 'list<string>',
                ],
            ],
            'arrayReversePreserveNonEmptiness' => [
                'code' => '<?php
                    /** @param string[] $arr */
                    function getOrderings(array $arr): int {
                        if ($arr) {
                            $next = null;
                            foreach (array_reverse($arr) as $v) {
                                $next = 1;
                            }
                            return $next;
                        }

                        return 2;
                    }',
            ],
            'inferArrayMapReturnType' => [
                'code' => '<?php
                    /** @return array<string> */
                    function Foo(DateTime ...$dateTimes) : array {
                        return array_map(
                            function ($dateTime) {
                                return ($dateTime->format("c"));
                            },
                            $dateTimes
                        );
                    }',
            ],
            'inferArrayMapArrowFunctionReturnType' => [
                'code' => '<?php
                    /** @return array<string> */
                    function Foo(DateTime ...$dateTimes) : array {
                        return array_map(
                            fn ($dateTime) => ($dateTime->format("c")),
                            $dateTimes
                        );
                    }',
                'assertions' => [],
                'ignored_issues' => [],
                'php_version' => '7.4',
            ],
            'arrayPad' => [
                'code' => '<?php
                    $a = array_pad(["foo" => 1, "bar" => 2], 10, 123);
                    $b = array_pad(["a", "b", "c"], 10, "x");
                    /** @var list<int> $list */
                    $c = array_pad($list, 10, 0);
                    /** @var array<string, string> $array */
                    $d = array_pad($array, 10, "");',
                'assertions' => [
                    '$a' => 'non-empty-array<int|string, int>',
                    '$b' => 'non-empty-list<string>',
                    '$c' => 'non-empty-list<int>',
                    '$d' => 'non-empty-array<int|string, string>',
                ],
            ],
            'arrayPadDynamicSize' => [
                'code' => '<?php
                    function getSize(): int { return random_int(1, 10); }

                    $a = array_pad(["foo" => 1, "bar" => 2], getSize(), 123);
                    $b = array_pad(["a", "b", "c"], getSize(), "x");
                    /** @var list<int> $list */
                    $c = array_pad($list, getSize(), 0);
                    /** @var array<string, string> $array */
                    $d = array_pad($array, getSize(), "");',
                'assertions' => [
                    '$a' => 'array<int|string, int>',
                    '$b' => 'list<string>',
                    '$c' => 'list<int>',
                    '$d' => 'array<int|string, string>',
                ],
            ],
            'arrayPadZeroSize' => [
                'code' => '<?php
                    /** @var array $arr */
                    $result = array_pad($arr, 0, null);',
                'assertions' => [
                    '$result' => 'array<array-key, mixed|null>',
                ],
            ],
            'arrayPadTypeCombination' => [
                'code' => '<?php
                    $a = array_pad(["foo" => 1, "bar" => "two"], 5, false);
                    $b = array_pad(["a", 2, 3.14], 5, null);
                    /** @var list<string|bool> $list */
                    $c = array_pad($list, 5, 0);
                    /** @var array<string, string> $array */
                    $d = array_pad($array, 5, null);',
                'assertions' => [
                    '$a' => 'non-empty-array<int|string, false|int|string>',
                    '$b' => 'non-empty-list<float|int|null|string>',
                    '$c' => 'non-empty-list<bool|int|string>',
                    '$d' => 'non-empty-array<int|string, null|string>',
                ],
            ],
            'arrayPadMixed' => [
                'code' => '<?php
                    /** @var array{foo: mixed, bar: mixed} $arr */
                    $a = array_pad($arr, 5, null);
                    /** @var mixed $mixed */
                    $b = array_pad([$mixed, $mixed], 5, null);
                    /** @var list $list */
                    $c = array_pad($list, 5, null);
                    /** @var mixed[] $array */
                    $d = array_pad($array, 5, null);',
                'assertions' => [
                    '$a' => 'non-empty-array<int|string, mixed|null>',
                    '$b' => 'non-empty-list<mixed|null>',
                    '$c' => 'non-empty-list<mixed|null>',
                    '$d' => 'non-empty-array<array-key, mixed|null>',
                ],
            ],
            'arrayPadFallback' => [
                'code' => '<?php
                    /**
                     * @var mixed $mixed
                     * @psalm-suppress MixedArgument
                     */
                    $result = array_pad($mixed, $mixed, $mixed);',
                'assertions' => [
                    '$result' => 'array<array-key, mixed>',
                ],
            ],
            'arrayChunk' => [
                'code' => '<?php
                    /** @var array{a: int, b: int, c: int, d: int} $arr */
                    $a = array_chunk($arr, 2);
                    /** @var list<string> $list */
                    $b = array_chunk($list, 2);
                    /** @var array<string, float> $arr */
                    $c = array_chunk($arr, 2);
                    ',
                'assertions' => [
                    '$a' => 'list<non-empty-list<int>>',
                    '$b' => 'list<non-empty-list<string>>',
                    '$c' => 'list<non-empty-list<float>>',
                ],
            ],
            'arrayChunkPreservedKeys' => [
                'code' => '<?php
                    /** @var array{a: int, b: int, c: int, d: int} $arr */
                    $a = array_chunk($arr, 2, true);
                    /** @var list<string> $list */
                    $b = array_chunk($list, 2, true);
                    /** @var array<string, float> $arr */
                    $c = array_chunk($arr, 2, true);',
                'assertions' => [
                    '$a' => 'list<non-empty-array<string, int>>',
                    '$b' => 'list<non-empty-array<int, string>>',
                    '$c' => 'list<non-empty-array<string, float>>',
                ],
            ],
            'arrayChunkPreservedKeysExplicitFalse' => [
                'code' => '<?php
                    /** @var array<string, string> $arr */
                    $result = array_chunk($arr, 2, false);',
                'assertions' => [
                    '$result' => 'list<non-empty-list<string>>',
                ],
            ],
            'arrayChunkMixed' => [
                'code' => '<?php
                    /** @var array{a: mixed, b: mixed, c: mixed} $arr */
                    $a = array_chunk($arr, 2);
                    /** @var list<mixed> $list */
                    $b = array_chunk($list, 2);
                    /** @var mixed[] $arr */
                    $c = array_chunk($arr, 2);',
                'assertions' => [
                    '$a' => 'list<non-empty-list<mixed>>',
                    '$b' => 'list<non-empty-list<mixed>>',
                    '$c' => 'list<non-empty-list<mixed>>',
                ],
            ],
            'arrayChunkFallback' => [
                'code' => '<?php
                    /**
                     * @var mixed $mixed
                     * @psalm-suppress MixedArgument
                     */
                    $result = array_chunk($mixed, $mixed, $mixed);',
                'assertions' => [
                    '$result' => 'list<array<array-key, mixed>>',
                ],
            ],
            'arrayMapPreserveNonEmptiness' => [
                'code' => '<?php
                    /**
                     * @psalm-param non-empty-list<string> $strings
                     * @psalm-return non-empty-list<int>
                     */
                    function foo(array $strings): array {
                        return array_map("intval", $strings);
                    }'
            ],
            'SKIPPED-arrayMapZip' => [
                'code' => '<?php
                    /**
                     * @return array<int, array{string,?string}>
                     */
                    function getCharPairs(string $line) : array {
                        $chars = str_split($line);
                        return array_map(
                            null,
                            $chars,
                            array_slice($chars, 1)
                        );
                    }'
            ],
            'arrayFillKeys' => [
                'code' => '<?php
                    $keys = [1, 2, 3];
                    $result = array_fill_keys($keys, true);',
                'assertions' => [
                    '$result' => 'array<int, true>',
                ],
            ],
            'shuffle' => [
                'code' => '<?php
                    $array = ["foo" => 123, "bar" => 456];
                    shuffle($array);
                    $emptyArray = [];
                    shuffle($emptyArray);',
                'assertions' => [
                    '$array' => 'non-empty-list<int>',
                    '$emptyArray' => 'list<never>',
                ],
            ],
            'sort' => [
                'code' => '<?php
                    $array = ["foo" => 123, "bar" => 456];
                    sort($array);
                    $emptyArray = [];
                    sort($emptyArray);',
                'assertions' => [
                    '$array' => 'non-empty-list<int>',
                    '$emptyArray' => 'list<never>',
                ],
            ],
            'rsort' => [
                'code' => '<?php
                    $array = ["foo" => 123, "bar" => 456];
                    rsort($array);
                    $emptyArray = [];
                    rsort($emptyArray);',
                'assertions' => [
                    '$array' => 'non-empty-list<int>',
                    '$emptyArray' => 'list<never>',
                ],
            ],
            'usort' => [
                'code' => '<?php
                    function baz (int $a, int $b): int { return $a <=> $b; }
                    $array = ["foo" => 123, "bar" => 456];
                    usort($array, "baz");
                    $emptyArray = [];
                    usort($emptyArray, "baz");',
                'assertions' => [
                    '$array' => 'non-empty-list<int>',
                    '$emptyArray' => 'list<never>',
                ],
            ],
            'closureParamConstraintsMet' => [
                'code' => '<?php
                    class A {}
                    class B {}

                    $test = [new A(), new B()];

                    usort(
                        $test,
                        /**
                         * @param A|B $a
                         * @param A|B $b
                         */
                        function($a, $b): int
                        {
                            return $a === $b ? 1 : -1;
                        }
                    );'
            ],
            'specialCaseArrayFilterOnSingleEntry' => [
                'code' => '<?php
                    /** @psalm-return list<int> */
                    function makeAList(int $ofThisInteger): array {
                        return array_filter([$ofThisInteger]);
                    }'
            ],
            'arrayMapWithEmptyArrayReturn' => [
                'code' => '<?php
                    /**
                     * @param array<array<string>> $elements
                     * @return list<string>
                     */
                    function resolvePossibleFilePaths($elements) : array
                    {
                        return array_values(
                            array_filter(
                                array_merge(
                                    ...array_map(
                                        function (array $element) : array {
                                            if (rand(0,1) == 1) {
                                                return [];
                                            }
                                            return $element;
                                        },
                                        $elements
                                    )
                                )
                            )
                        );
                    }'
            ],
            'arrayFilterArrowFunction' => [
                'code' => '<?php
                    class A {}
                    class B {}

                    $a = \array_filter(
                        [new A(), new B()],
                        function($x) {
                            return $x instanceof B;
                        }
                    );

                    $b = \array_filter(
                        [new A(), new B()],
                        fn($x) => $x instanceof B
                    );',
                'assertions' => [
                    '$a' => 'array<int, B>',
                    '$b' => 'array<int, B>',
                ],
                'ignored_issues' => [],
                'php_version' => '7.4',
            ],
            'arrayMergeTwoExplicitLists' => [
                'code' => '<?php
                    /**
                     * @param list<int> $foo
                     */
                    function foo(array $foo) : void {}

                    $foo1 = [1, 2, 3];
                    $foo2 = [1, 4, 5];
                    foo(array_merge($foo1, $foo2));'
            ],
            'arrayMergeTwoPossiblyFalse' => [
                'code' => '<?php
                    $a = array_merge(
                        glob(__DIR__ . \'/stubs/*.php\'),
                        glob(__DIR__ . \'/stubs/DBAL/*.php\'),
                    );',
                'assertions' => [
                    '$a' => 'list<string>'
                ],
            ],
            'arrayReplaceTwoExplicitLists' => [
                'code' => '<?php
                    /**
                     * @param list<int> $foo
                     */
                    function foo(array $foo) : void {}

                    $foo1 = [1, 2, 3];
                    $foo2 = [1, 4, 5];
                    foo(array_replace($foo1, $foo2));'
            ],
            'arrayReplaceTwoPossiblyFalse' => [
                'code' => '<?php
                    $a = array_replace(
                        glob(__DIR__ . \'/stubs/*.php\'),
                        glob(__DIR__ . \'/stubs/DBAL/*.php\'),
                    );',
                'assertions' => [
                    '$a' => 'list<string>'
                ],
            ],
            'arrayMapPossiblyFalseIgnored' => [
                'code' => '<?php
                    function takesString(string $string): void {}

                    $date = new DateTime();

                    $a = [$date->format("Y-m-d")];

                    takesString($a[0]);
                    array_map("takesString", $a);',
            ],
            'arrayMapExplicitZip' => [
                'code' => '<?php
                    $as = ["key"];
                    $bs = ["value"];

                    return array_map(fn ($a, $b) => [$a => $b], $as, $bs);',
                'assertions' => [],
                'ignored_issues' => [],
                'php_version' => '7.4',
            ],
            'spliceTurnsintKeyedInputToList' => [
                'code' => '<?php
                    /**
                     * @psalm-param list<string> $elements
                     * @return list<string>
                     */
                    function bar(array $elements, int $index, string $element) : array {
                        array_splice($elements, $index, 0, [$element]);
                        return $elements;
                    }'
            ],
            'arrayChangeKeyCaseWithNonStringKeys' => [
                'code' => '<?php

                $a = [42, "A" => 42];
                echo array_change_key_case($a, CASE_LOWER)[0];'
            ],
            'mapInterfaceMethod' => [
                'code' => '<?php
                    interface MapperInterface {
                        public function map(string $s): int;
                    }

                    /**
                     * @param list<string> $strings
                     * @return list<int>
                     */
                    function mapList(MapperInterface $m, array $strings): array {
                        return array_map([$m, "map"], $strings);
                    }'
            ],
            'arrayShiftComplexArray' => [
                'code' => '<?php
                    /**
                     * @param list<string> $slugParts
                     */
                    function foo(array $slugParts) : void {
                        if (!$slugParts) {
                            $slugParts = [""];
                        }
                        array_shift($slugParts);
                        if (!empty($slugParts)) {}
                    }'
            ],
            'arrayMergeKeepLastKeysAndType' => [
                'code' => '<?php
                    /**
                     * @param array{A: int} $a
                     * @param array<string, string> $b
                     *
                     * @return array{A: int}
                     */
                    function merger(array $a, array $b) : array {
                        return array_merge($b, $a);
                    }'
            ],
            'arrayMergeKeepFirstKeysSameType' => [
                'code' => '<?php
                    /**
                     * @param array{A: int} $a
                     * @param array<string, int> $b
                     *
                     * @return array{A: int}
                     */
                    function merger(array $a, array $b) : array {
                        return array_merge($a, $b);
                    }'
            ],
            'arrayReplaceKeepLastKeysAndType' => [
                'code' => '<?php
                    /**
                     * @param array{A: int} $a
                     * @param array<string, string> $b
                     *
                     * @return array{A: int}
                     */
                    function merger(array $a, array $b) : array {
                        return array_replace($b, $a);
                    }'
            ],
            'arrayReplaceKeepFirstKeysSameType' => [
                'code' => '<?php
                    /**
                     * @param array{A: int} $a
                     * @param array<string, int> $b
                     *
                     * @return array{A: int}
                     */
                    function merger(array $a, array $b) : array {
                        return array_replace($a, $b);
                    }'
            ],
            'filteredArrayCanBeEmpty' => [
                'code' => '<?php
                    /**
                      * @return string|null
                      */
                    function thing() {
                        if(rand(0,1) === 1) {
                            return "data";
                        } else {
                            return null;
                        }
                    }
                    $list = [thing(),thing(),thing()];
                    $list = array_filter($list);
                    if (!empty($list)) {}'
            ],
            'arrayShiftOnMixedOrEmptyArray' => [
                'code' => '<?php
                    /**
                     * @param mixed|array<never, never> $lengths
                     */
                    function doStuff($lengths): void {
                        /** @psalm-suppress MixedArgument, MixedAssignment */
                        $length = array_shift($lengths);
                        if ($length !== null) {}
                    }'
            ],
            'countOnListIntoTuple' => [
                'code' => '<?php
                    /** @param array{string, string} $tuple */
                    function foo(array $tuple) : void {}

                    /** @param list<string> $list */
                    function bar(array $list) : void {
                        if (count($list) === 2) {
                            foo($list);
                        }
                    }'
            ],
            'arrayColumnwithKeyedArrayWithoutRedundantUnion' => [
                'code' => '<?php
                    /**
                     * @param array<string, array{x?:int, y?:int, width?:int, height?:int}> $foos
                     */
                    function foo(array $foos): void {
                        array_multisort($formLayoutFields, SORT_ASC, array_column($foos, "y"));
                    }'
            ],
            'arrayMapGenericObject' => [
                'code' => '<?php
                    /**
                     * @template T
                     */
                    interface Container
                    {
                        /**
                         * @return T
                         */
                        public function get(string $name);
                    }

                    /**
                     * @param Container<stdClass> $container
                     * @param array<string> $data
                     * @return array<stdClass>
                     */
                    function bar(Container $container, array $data): array {
                        return array_map(
                            [$container, "get"],
                            $data
                        );
                    }'
            ],
            'arrayMapShapeAndGenericArray' => [
                'code' => '<?php
                    /** @return string[] */
                    function getLine(): array { return ["a", "b"]; }

                    $line = getLine();

                    if (empty($line[0])) { // converts array<string> to array{0:string}<string>
                        throw new InvalidArgumentException;
                    }

                    $line = array_map( // should not destroy <string> part
                        function($val) { return (int)$val; },
                        $line
                    );
                ',
                'assertions' => [
                    '$line===' => 'array{0: int}<array-key, int>',
                ],
            ],
            'arrayUnshiftOnEmptyArrayMeansNonEmptyList' => [
                'code' => '<?php
                    /**
                     * @return non-empty-list<string>
                     */
                    function foo(): array
                    {
                        $a = [];

                        array_unshift($a, "string");

                        return $a;
                    }',
            ],
            'keepClassStringInOffsetThroughArrayMerge' => [
                'code' => '<?php

                    class A {
                        /** @var array<class-string, string> */
                        private array $a;

                        public function __construct() {
                            $this->a = [];
                        }

                        public function handle(): void {
                            $b = [A::class => "d"];
                            $this->a = array_merge($this->a, $b);
                        }
                    }
                    ',
            ],
        ];
    }

    /**
     * @return iterable<string,array{code:string,error_message:string,ignored_issues?:list<string>,php_version?:string}>
     */
    public function providerInvalidCodeParse(): iterable
    {
        return [
            'arrayFilterWithoutTypes' => [
                'code' => '<?php
                    $e = array_filter(
                        ["a" => 5, "b" => 12, "c" => null],
                        function(?int $i) {
                            return $_GET["a"];
                        }
                    );',
                'error_message' => 'MixedArgumentTypeCoercion',
                'ignored_issues' => ['MissingClosureParamType', 'MissingClosureReturnType'],
            ],
            'arrayFilterUseMethodOnInferrableInt' => [
                'code' => '<?php
                    $a = array_filter([1, 2, 3, 4], function ($i) { return $i->foo(); });',
                'error_message' => 'InvalidMethodCall',
            ],
            'arrayMapUseMethodOnInferrableInt' => [
                'code' => '<?php
                    $a = array_map(function ($i) { return $i->foo(); }, [1, 2, 3, 4]);',
                'error_message' => 'InvalidMethodCall',
            ],
            'arrayMapWithNonCallableStringArray' => [
                'code' => '<?php
                    $foo = ["one", "two"];
                    array_map($foo, ["hello"]);',
                'error_message' => 'InvalidArgument',
            ],
            'arrayMapWithNonCallableIntArray' => [
                'code' => '<?php
                    $foo = [1, 2];
                    array_map($foo, ["hello"]);',
                'error_message' => 'InvalidArgument',
            ],
            'arrayFilterBadArgs' => [
                'code' => '<?php
                    function foo(int $i) : bool {
                      return true;
                    }

                    array_filter(["hello"], "foo");',
                'error_message' => 'InvalidScalarArgument',
            ],
            'arrayFillPositiveConstantLength' => [
                'code' => '<?php
                    count(array_fill(0, 1, 0)) === 0;',
                'error_message' => 'TypeDoesNotContainType'
            ],
            'arrayFilterTooFewArgs' => [
                'code' => '<?php
                    function foo(int $i, string $s) : bool {
                      return true;
                    }

                    array_filter([1, 2, 3], "foo");',
                'error_message' => 'TooFewArguments',
            ],
            'arrayMapBadArgs' => [
                'code' => '<?php
                    function foo(int $i) : bool {
                      return true;
                    }

                    array_map("foo", ["hello"]);',
                'error_message' => 'InvalidScalarArgument',
            ],
            'arrayMapTooFewArgs' => [
                'code' => '<?php
                    function foo(int $i, string $s) : bool {
                      return true;
                    }

                    array_map("foo", [1, 2, 3]);',
                'error_message' => 'TooFewArguments',
            ],
            'arrayMapTooManyArgs' => [
                'code' => '<?php
                    function foo() : bool {
                      return true;
                    }

                    array_map("foo", [1, 2, 3]);',
                'error_message' => 'TooManyArguments',
            ],
            'arrayReduceInvalidClosureTooFewArgs' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    $direct_closure_result = array_reduce(
                        $arr,
                        function() : int {
                            return 5;
                        },
                        1
                    );',
                'error_message' => 'InvalidArgument',
                'ignored_issues' => ['MixedTypeCoercion'],
            ],
            'arrayReduceInvalidItemType' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    $direct_closure_result = array_reduce(
                        $arr,
                        function (int $carry, stdClass $item) {
                            return $_GET["boo"];
                        },
                        1
                    );',
                'error_message' => 'InvalidArgument',
                'ignored_issues' => ['MissingClosureReturnType'],
            ],
            'arrayReduceInvalidCarryType' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    $direct_closure_result = array_reduce(
                        $arr,
                        function (stdClass $carry, int $item) {
                            return $_GET["boo"];
                        },
                        1
                    );',
                'error_message' => 'InvalidArgument',
                'ignored_issues' => ['MissingClosureReturnType'],
            ],
            'arrayReduceInvalidCarryOutputType' => [
                'code' => '<?php
                    $arr = [2, 3, 4, 5];

                    $direct_closure_result = array_reduce(
                        $arr,
                        function (int $carry, int $item) : stdClass {
                            return new stdClass;
                        },
                        1
                    );',
                'error_message' => 'InvalidArgument',
            ],
            'arrayPopNotNull' => [
                'code' => '<?php
                    function expectsInt(int $a) : void {}

                    /**
                     * @param array<array-key, array{item:int}> $list
                     */
                    function test(array $list) : void
                    {
                        while (!empty($list)) {
                            $tmp = array_pop($list);
                            if ($tmp === null) {}
                        }
                    }',
                'error_message' => 'DocblockTypeContradiction',
            ],
            'usortInvalidCallableString' => [
                'code' => '<?php
                    $a = [[1], [2], [3]];
                    usort($a, "strcmp");',
                'error_message' => 'InvalidArgument',
            ],
            'arrayShiftUndefinedVariable' => [
                'code' => '<?php
                    /** @psalm-suppress MissingParamType */
                    function foo($data): void {
                        /** @psalm-suppress MixedArgument */
                        array_unshift($data, $a);
                    }',
                'error_message' => 'UndefinedVariable',
            ],
            'arrayFilterTKeyedArray' => [
                'code' => '<?php
                    /** @param list<int> $ints */
                    function ints(array $ints) : void {}
                    $brr = array_filter([2,3,0,4,5]);
                    ints($brr);',
                'error_message' => 'InvalidArgument',
            ],
            'usortOneParamInvalid' => [
                'code' => '<?php
                    $list = [3, 2, 5, 9];
                    usort($list, fn(int $a, string $b): int => (int) ($a > $b));',
                'error_message' => 'InvalidScalarArgument',
                'ignored_issues' => [],
                'php_version' => '7.4',
            ],
            'usortInvalidComparison' => [
                'code' => '<?php
                    $arr = [["one"], ["two"], ["three"]];

                    usort(
                        $arr,
                        function (string $a, string $b): int {
                            return strcmp($a, $b);
                        }
                    );',
                'error_message' => 'InvalidArgument',
            ],
            'arrayMergeKeepFirstKeysButNotType' => [
                'code' => '<?php
                    /**
                     * @param array{A: int} $a
                     * @param array<string, string> $b
                     *
                     * @return array{A: int}
                     */
                    function merger(array $a, array $b) : array {
                        return array_merge($a, $b);
                    }',
                'error_message' => 'LessSpecificReturnStatement - src' . DIRECTORY_SEPARATOR . 'somefile.php:9:32 - The type \'array{A: int|string}<string, string>\' is more general',
            ],
            'arrayReplaceKeepFirstKeysButNotType' => [
                'code' => '<?php
                    /**
                     * @param array{A: int} $a
                     * @param array<string, string> $b
                     *
                     * @return array{A: int}
                     */
                    function merger(array $a, array $b) : array {
                        return array_replace($a, $b);
                    }',
                'error_message' => 'LessSpecificReturnStatement - src' . DIRECTORY_SEPARATOR . 'somefile.php:9:32 - The type \'array{A: int|string}<string, string>\' is more general',
            ],
            'arrayWalkOverObject' => [
                'code' => '<?php
                    $o = new stdClass();
                    array_walk($o, "var_dump");
                ',
                'error_message' => 'RawObjectIteration',
            ],
            'arrayWalkRecursiveOverObject' => [
                'code' => '<?php
                    $o = new stdClass();
                    array_walk_recursive($o, "var_dump");
                ',
                'error_message' => 'RawObjectIteration',
            ],
        ];
    }
}
