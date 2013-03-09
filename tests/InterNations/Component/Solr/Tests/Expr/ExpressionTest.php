<?php
namespace InterNations\Component\Solr\Tests\Expr;

use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\Solr\Expr\Expression;
use InterNations\Component\Solr\Expr\DateTimeExpression;
use InterNations\Component\Solr\Expr\PhraseExpression;
use InterNations\Component\Solr\Expr\WildcardExpression;
use InterNations\Component\Solr\Expr\GroupExpression;
use InterNations\Component\Solr\Expr\BoostExpression;
use InterNations\Component\Solr\Expr\FieldExpression;
use InterNations\Component\Solr\Expr\ProximityExpression;
use InterNations\Component\Solr\Expr\RangeExpression;
use InterNations\Component\Solr\Expr\FuzzyExpression;
use InterNations\Component\Solr\Expr\BooleanExpression;
use DateTime;
use DateTimeZone;

class ExpressionTest extends AbstractTestCase
{
    public function testPhraseExpression()
    {
        $this->assertSame('"foo\:bar"', (string) new PhraseExpression('foo:bar'));
        $this->assertSame('"foo"', (string) new PhraseExpression('foo'));
        $this->assertSame('"völ"', (string) new PhraseExpression('völ', 10));
        $this->assertSame('"val1"', (string) new PhraseExpression('val1'));
        $this->assertSame('"foo bar"', (string) new PhraseExpression('foo bar'));
    }

    public function testWildcardExprEscapesSuffixAndPrefixButNotWildcard()
    {
        $this->assertSame('foo\:bar?bar', (string) new WildcardExpression('?', 'foo:bar', 'bar'));
        $this->assertSame('foo*bar\:foo', (string) new WildcardExpression('*', 'foo', 'bar:foo'));
        $this->assertSame('foo\:bar?', (string) new WildcardExpression('?', 'foo:bar'));
    }

    public function testPhrasesAndWildcards()
    {
        $this->assertSame('"foo bar*baz"', (string) new WildcardExpression('*', new PhraseExpression('foo bar'), 'baz'));
        $this->assertSame('"foo bar\:baz*baz"', (string) new WildcardExpression('*', new PhraseExpression('foo bar:baz'), 'baz'));
    }

    public function testGroupingPhrasesAndTerms()
    {
        $this->assertSame('(foo\:bar "foo bar")', (string) new GroupExpression(['foo:bar', new PhraseExpression('foo bar')]));
        $this->assertSame(
            '(foo* "foo bar")',
            (string) new GroupExpression([new WildcardExpression('*', 'foo'), new PhraseExpression('foo bar')])
        );
        $this->assertSame('', (string) new GroupExpression([]));
        $this->assertSame('("foo bar")', (string) new GroupExpression([null, false, '', new PhraseExpression('foo bar')]));
    }

    public function testBoostingPhrasesTermsAndGroups()
    {
        $this->assertSame('foo^10', (string) new BoostExpression(10, 'foo'));
        $this->assertSame('foo^10', (string) new BoostExpression('10dsfsd', 'foo'));
        $this->assertSame('foo^10.2', (string) new BoostExpression('10.2dsfsd', 'foo'));
        $this->assertSame('foo^10.1', (string) new BoostExpression(10.1, 'foo'));
        $this->assertSame('foo*^200', (string) new BoostExpression(200, new WildcardExpression('*', 'foo')));
        $this->assertSame('(foo bar)^200', (string) new BoostExpression(200, new GroupExpression(['foo', 'bar'])));
    }

    public function testFieldExpression()
    {
        $this->assertSame('field:value\:foo', (string) new FieldExpression('field', 'value:foo'));
        $this->assertSame(
            'field:(foo "foo bar")',
            (string) new FieldExpression('field', new GroupExpression(['foo', new PhraseExpression('foo bar')]))
        );
        $this->assertSame('fie\-ld:foo', (string) new FieldExpression('fie-ld', 'foo'));
    }

    public function testBooleanExpression()
    {
        $this->assertSame(
            '+(foo bar)',
            (string) new BooleanExpression(BooleanExpression::OPERATOR_REQUIRED, new GroupExpression(['foo', 'bar']))
        );
        $this->assertSame(
            '+"foo bar"',
            (string) new BooleanExpression(BooleanExpression::OPERATOR_REQUIRED, new PhraseExpression('foo bar'))
        );
        $this->assertSame('+foo', (string) new BooleanExpression(BooleanExpression::OPERATOR_REQUIRED, 'foo'));
        $this->assertSame(
            '+foo?bar',
            (string) new BooleanExpression(BooleanExpression::OPERATOR_REQUIRED, new WildcardExpression('?', 'foo', 'bar'))
        );
        $this->assertSame('-foo', (string) new BooleanExpression(BooleanExpression::OPERATOR_PROHIBITED, 'foo'));
        $this->assertSame(
            '-"foo bar"',
            (string) new BooleanExpression(BooleanExpression::OPERATOR_PROHIBITED, new PhraseExpression('foo bar'))
        );
        $this->assertSame(
            '-"foo?bar baz"',
            (string) new BooleanExpression(
                BooleanExpression::OPERATOR_PROHIBITED,
                new WildcardExpression('?', 'foo', new PhraseExpression('bar baz'))
            )
        );
    }

    public function testProximityExpression()
    {
        $this->assertSame('"foo bar"~100', (string) new ProximityExpression('foo', 'bar', 100));
        $this->assertSame('"bar foo"~200', (string) new ProximityExpression('bar', 'foo', 200));
    }

    public function testRangeExpression()
    {
        $this->assertSame('[foo TO bar]', (string) new RangeExpression('foo', 'bar', true));
        $this->assertSame('[foo TO bar]', (string) new RangeExpression('foo', 'bar'));
        $this->assertSame('[foo TO "foo bar"]', (string) new RangeExpression('foo', new PhraseExpression('foo bar')));
        $this->assertSame('{foo TO "foo bar"}', (string) new RangeExpression('foo', new PhraseExpression('foo bar'), null, false));
        $this->assertSame(
            '{foo TO "foo bar?"}',
            (string) new RangeExpression('foo', new WildcardExpression('?', new PhraseExpression('foo bar')), false)
        );
    }

    public function testFuzzyExpression()
    {
        $this->assertSame('foo~', (string) new FuzzyExpression('foo'));
        $this->assertSame('foo~0.8', (string) new FuzzyExpression('foo', 0.8));
        $this->assertSame('foo~0', (string) new FuzzyExpression('foo', 0));
    }

    public function testDateExpression()
    {
        $this->assertSame(
            '2012-12-13T14:15:16Z',
            (string) new DateTimeExpression(new DateTime('2012-12-13 15:15:16', new DateTimeZone('Europe/Berlin')))
        );
    }

    public function testGroupExpression()
    {
        $this->assertSame('(1 2 3)', (string) new GroupExpression([1, 2, 3]));
        $this->assertSame('(one two three)', (string) new GroupExpression(['one', 'two', 'three']));
        $this->assertSame('(one\: two three)', (string) new GroupExpression(['one:', 'two', 'three']));
        $this->assertSame('("one two" "three four")', (string) new GroupExpression(['one two', 'three four']));
    }

    public function testPlaceholderReplacement()
    {
        $expr = new Expression('field:<placeholder>');
        $expr->setPlaceholder('placeholder', 'foo bar');

        $this->assertSame('field:"foo bar"', (string) $expr);
    }

    public function testPlaceholderReplacement_Escapes()
    {
        $expr = new Expression('field:<placeholder>');
        $expr->setPlaceholder('placeholder', 'foo:bar');

        $this->assertSame('field:"foo\:bar"', (string) $expr);
    }

    public function testPlaceholderReplacement_MultiplePlaceholders()
    {
        $expr = new Expression('field1:<p1> AND field2:<p2>');
        $expr->setPlaceholder('p1', '?')
            ->setPlaceholder('p2', '*');

        $this->assertSame('field1:"\?" AND field2:"\*"', (string) $expr);
    }

    public function testPlaceholderReplacement_WithExpressions()
    {
        $expr = new Expression('field:<p>');
        $expr->setPlaceholder('p', new WildcardExpression('*'));

        $this->assertSame('field:*', (string) $expr);
    }

    public function testPlaceholderReplacement_DateTime()
    {
        $expr = new Expression('field:<p>');
        $expr->setPlaceholder('p', new DateTime('2012-12-13 14:15:16'));

        $this->assertSame('field:2012-12-13T13:15:16Z', (string) $expr);
    }

    public function testPlaceholderReplacement_Array()
    {
        $expr = new Expression('field:<p>');
        $expr->setPlaceholder('p', [1,2,3]);

        $this->assertSame('field:(1 2 3)', (string) $expr);
    }
}