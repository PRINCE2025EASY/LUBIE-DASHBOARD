<?php

declare(strict_types=1);

namespace Lubie\Tests\Unit;

use InvalidArgumentException;
use Lubie\Db\SqlScript;
use Lubie\Http\HttpException;
use Lubie\Reports\CsvWriter;
use Lubie\Support\Dates;
use Lubie\Support\Money;
use Lubie\Validation\Validator;
use PHPUnit\Framework\TestCase;

/** UT-SUP: money, validation, CSV safety, SQL splitting, date display. */
final class SupportTest extends TestCase
{
    public function testMoneyParsesAndFormats(): void
    {
        $this->assertSame(240000000, Money::toPesewas('2,400,000.00'));
        $this->assertSame(1050, Money::toPesewas('10.5'));
        $this->assertSame(-1, Money::toPesewas('-0.01'));
        $this->assertNull(Money::toPesewas(''));
        $this->assertSame('2400000.00', Money::toString(240000000));
        $this->assertSame('-0.05', Money::toString(-5));
        $this->assertSame('GH₵ 2,400,000.00', Money::format(240000000));
        $this->assertSame('Not available', Money::format(null));
    }

    public function testMoneyRejectsThreeDecimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toPesewas('1.005');
    }

    public function testMoneyHalfUpRounding(): void
    {
        $this->assertSame(1, Money::mulDiv(1, 5000, 10000));
        $this->assertSame(0, Money::mulDiv(1, 4999, 10000));
        $this->assertSame(-1, Money::mulDiv(-1, 5000, 10000));
    }

    public function testValidatorNormalisesGhanaPhone(): void
    {
        $d = Validator::of(['p' => '024 123 4567', 'q' => '+233241234567'])->phone('p')->phone('q')->validated();
        $this->assertSame('+233241234567', $d['p']);
        $this->assertSame('+233241234567', $d['q']);
    }

    public function testValidatorCollectsFieldErrors(): void
    {
        try {
            Validator::of(['name' => '', 'pct' => '101', 'when' => '31/02/2026', 'amt' => 'abc', 'role' => 'boss'])
                ->string('name', 10)->percent('pct')->date('when')->money('amt')->in('role', ['pm', 'qs'])->phone('tel')->validated();
            $this->fail('Expected validation error');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame(['name', 'pct', 'when', 'amt', 'role'], array_keys($e->fields));
        }
    }

    public function testValidatorAcceptsGoodValues(): void
    {
        $d = Validator::of(['pct' => '45.5', 'when' => '2026-03-10', 'amt' => '1,250.75', 'uuid' => '0F8FAD5B-D9CB-469F-A165-70867728950E'])
            ->percent('pct')->date('when')->money('amt')->uuid('uuid')->validated();
        $this->assertSame('2026-03-10', $d['when']);
        $this->assertSame('0f8fad5b-d9cb-469f-a165-70867728950e', $d['uuid']);
    }

    public function testCsvFormulaInjectionGuard(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", CsvWriter::safe('=HYPERLINK("x")'));
        $this->assertSame("'+cmd", CsvWriter::safe('+cmd'));
        $this->assertSame("'@SUM(A1)", CsvWriter::safe('@SUM(A1)'));
        $this->assertSame('-1250.00', CsvWriter::safe('-1250.00'), 'Negative numbers stay numbers');
        $this->assertSame('', CsvWriter::safe(null));
        $out = fopen('php://memory', 'w+');
        CsvWriter::write($out, ['A', 'B'], [['x,y', '=1+1']]);
        rewind($out);
        $this->assertSame("\xEF\xBB\xBFA,B\n\"x,y\",'=1+1\n", stream_get_contents($out));
    }

    public function testSqlSplitterRespectsQuotesAndComments(): void
    {
        $sql = "-- comment; with semicolon\nINSERT INTO t VALUES ('a;b', \"c;d\", 'it''s');\n/* block; */ SELECT `x;y` FROM t; # trailing\nSELECT 1";
        $parts = SqlScript::split($sql);
        $this->assertCount(3, $parts);
        $this->assertStringContainsString("'a;b'", $parts[0]);
        $this->assertStringContainsString("'it''s'", $parts[0]);
        $this->assertSame('SELECT 1', $parts[2]);
    }

    public function testSchemaFilesSplitCleanly(): void
    {
        $root = dirname(__DIR__, 2) . '/database/';
        foreach (['schema.sql', 'seed_production.sql', 'seed_demo.sql', 'remove_demo.sql'] as $f) {
            $this->assertNotEmpty(SqlScript::split((string) file_get_contents($root . $f)), $f);
        }
    }

    public function testUkDates(): void
    {
        $this->assertSame('05/03/2026', Dates::uk('2026-03-05'));
        $this->assertSame('05/03/2026', Dates::uk('2026-03-05 10:00:00'));
        $this->assertSame('', Dates::uk(null));
    }

    public function testClientIpBehindTrustedProxy(): void
    {
        $h = ['x-forwarded-for' => '1.1.1.1, 41.66.10.20, 10.0.0.5'];
        $this->assertSame('172.16.0.9', \Lubie\Http\Request::clientIp($h, '172.16.0.9', 0), 'No trust: socket address');
        $this->assertSame('10.0.0.5', \Lubie\Http\Request::clientIp($h, '172.16.0.9', 1));
        $this->assertSame('41.66.10.20', \Lubie\Http\Request::clientIp($h, '172.16.0.9', 2), 'Faked left-most entry ignored');
        $this->assertSame('172.16.0.9', \Lubie\Http\Request::clientIp(['x-forwarded-for' => 'junk'], '172.16.0.9', 1));
    }
}
