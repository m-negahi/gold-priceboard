<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use App\Modules\Auth\Infrastructure\Persistence\Models\User;
use App\Modules\Pricing\Infrastructure\Support\ManualPriceWorkbook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

final class ManualPriceWorkbookImportTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function workbook(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'price-import-test-');
        $this->paths[] = $path;
        (new ManualPriceWorkbook)->write($path, $rows);

        return new UploadedFile($path, 'prices.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_template_is_an_actual_excel_workbook_with_the_required_columns(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('panel.manual.template'))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        $this->paths[] = $path;
        $zip = new ZipArchive;
        self::assertTrue($zip->open($path));
        self::assertStringContainsString('xau-18k-g-irr', $zip->getFromName('xl/worksheets/sheet1.xml'));
        self::assertStringContainsString('IRR', $zip->getFromName('xl/worksheets/sheet1.xml'));
        self::assertStringContainsString('USD', $zip->getFromName('xl/worksheets/sheet1.xml'));
        self::assertStringContainsString('numFmtId="49"', $zip->getFromName('xl/styles.xml'));
        self::assertStringContainsString('<c r="C2" t="inlineStr" s="1">', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
    }

    public function test_preview_does_not_write_and_confirmation_preserves_exact_decimal_strings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $file = $this->workbook([
            ['symbol' => 'xau-18k-g-irr', 'label' => '', 'price' => '266400000', 'unit' => 'IRR'],
            ['symbol' => 'xau-oz-usd', 'label' => '', 'price' => '900719925474.0993', 'unit' => 'USD'],
        ]);

        $preview = $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertOk()->assertJsonPath('preview.hasErrors', false);
        $this->assertDatabaseCount('manual_prices', 0);
        $this->postJson(route('panel.manual.import.confirm'), ['token' => $preview->json('token'), 'rows' => [['symbol' => 'nist', 'price' => '1']]])->assertOk();

        $this->assertDatabaseHas('manual_prices', ['symbol' => 'xau-18k-g-irr', 'minor' => 266_400_000]);
        $this->assertDatabaseHas('manual_prices', ['symbol' => 'xau-oz-usd', 'minor' => 9_007_199_254_740_993]);
        $this->postJson(route('panel.manual.import.confirm'), ['token' => $preview->json('token')])->assertUnprocessable();
    }

    public function test_row_errors_block_all_writes_and_report_units_unknown_symbols_and_duplicates(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $file = $this->workbook([
            ['symbol' => 'xau-18k-g-irr', 'label' => '', 'price' => '266400000', 'unit' => 'IRR'],
            ['symbol' => 'xau-18k-g-irr', 'label' => '', 'price' => '266400001', 'unit' => 'IRR'],
            ['symbol' => 'nist', 'label' => '', 'price' => '1', 'unit' => 'IRR'],
            ['symbol' => 'xau-oz-usd', 'label' => '', 'price' => '4140.19', 'unit' => 'IRR'],
            ['symbol' => 'xag-925-g-irr', 'label' => '', 'price' => '1e4', 'unit' => 'IRR'],
        ]);

        $preview = $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertOk()->assertJsonPath('preview.hasErrors', true)
            ->assertJsonPath('preview.rows.1.error', 'نماد تکراری است.')
            ->assertJsonPath('preview.rows.2.error', 'نماد ناشناخته است.')
            ->assertJsonPath('preview.rows.3.error', 'واحد این نماد باید USD (دلار) باشد.');
        $this->postJson(route('panel.manual.import.confirm'), ['token' => $preview->json('token')])->assertUnprocessable();

        $this->assertDatabaseCount('manual_prices', 0);
    }

    public function test_formula_and_external_entity_workbooks_are_rejected_without_writes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $file = $this->workbook([['symbol' => 'xau-18k-g-irr', 'label' => '', 'price' => '266400000', 'unit' => 'IRR']]);
        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('<c r="C2" t="inlineStr" s="1">', '<c r="C2" t="inlineStr" s="1"><f>1+1</f>', $xml));
        $zip->close();

        $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertOk()->assertJsonPath('preview.hasErrors', true);
        $this->assertDatabaseCount('manual_prices', 0);

        $zip->open($file->getRealPath());
        $zip->addFromString('xl/worksheets/sheet1.xml', '<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]>'.$xml);
        $zip->close();
        $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertUnprocessable();
        $this->assertDatabaseCount('manual_prices', 0);
    }

    public function test_guests_and_customers_cannot_import_admin_prices(): void
    {
        $this->postJson(route('panel.manual.import.confirm'), ['token' => str_repeat('a', 40)])->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->get(route('panel.manual.template'))->assertNotFound();
    }

    public function test_compressed_oversized_workbooks_are_rejected_and_clear_the_previous_confirmation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $file = $this->workbook([['symbol' => 'xau-18k-g-irr', 'label' => '', 'price' => '266400000', 'unit' => 'IRR']]);
        $preview = $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertOk();

        $zip = new ZipArchive;
        $zip->open($file->getRealPath());
        $zip->addFromString('xl/extra.xml', str_repeat('a', 8_000_001));
        $zip->close();

        $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertUnprocessable();
        $this->postJson(route('panel.manual.import.confirm'), ['token' => $preview->json('token')])->assertUnprocessable();
        $this->assertDatabaseCount('manual_prices', 0);
    }

    public function test_malformed_zip_uploads_return_validation_errors_without_writes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $file = UploadedFile::fake()->createWithContent('prices.xlsx', 'invalid workbook');

        $this->postJson(route('panel.manual.import.preview'), ['workbook' => $file])->assertUnprocessable();
        $this->assertDatabaseCount('manual_prices', 0);
    }
}
