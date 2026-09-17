<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function sample(string $name): string
    {
        $path = dirname(base_path()).'/Indents and Bills for testing/'.$name;
        if (! is_file($path)) {
            $this->markTestSkipped('Local sample file is unavailable.');
        }

        return $path;
    }

    public function test_real_indent_upload_reads_complete_identity_totals_and_all_lines(): void
    {
        $this->seedAndSignIn();
        $file = new UploadedFile($this->sample('Proforma Indent 2.pdf'), 'Proforma Indent 2.pdf', 'application/pdf', null, true);
        $this->followingRedirects()->post('/uploads/excise', ['indent' => $file])
            ->assertOk()
            ->assertSee('Upload read successfully')
            ->assertSee('9 indent lines read')
            ->assertSee('IND2026DEPOLD002230733')
            ->assertSee('219,970.00')
            ->assertSee('251,930.00')
            ->assertViewHas('preview', function ($preview) {
                $this->assertCount(9, $preview['lines']);
                $this->assertSame(828, $preview['bottles']);
                $this->assertSame('0547QMG', $preview['lines'][8]['code']);
                $this->assertEquals(11901, $preview['lines'][6]['amount']);
                $this->assertEqualsWithDelta(1950.083333, $preview['lines'][5]['bottle_price'], 0.00001);

                return true;
            });
    }

    public function test_real_pos_upload_reads_all_three_quantity_sheets(): void
    {
        $this->seedAndSignIn();
        $name = 'item_wise_sales_report_FROM_10 Jul 2026_TO_22 Aug 2026_Fetched On_23 Aug 2026_BY_sip-society.xlsx';
        $file = new UploadedFile($this->sample($name), $name, null, null, true);
        $this->followingRedirects()->post('/uploads/pos', ['report' => $file])
            ->assertOk()
            ->assertSee('Upload read successfully')
            ->assertSee('141 liquor POS items read')
            ->assertViewHas('preview', function ($preview) {
                $this->assertCount(3, $preview['sheets']);
                $this->assertSame(141, $preview['items']);
                $this->assertEquals(6676, $preview['quantity']);
                $this->assertEquals(12, $preview['comp']);
                $this->assertEquals(285, $preview['nc']);
                $this->assertSame('2026-07-10', $preview['from']);

                return true;
            });
    }

    public function test_wrong_file_type_returns_visible_validation_error(): void
    {
        $this->seedAndSignIn();
        $this->from('/uploads/excise')->followingRedirects()->post('/uploads/excise', [
            'indent' => UploadedFile::fake()->create('invalid.txt', 1, 'text/plain'),
        ])
            ->assertOk()
            ->assertSee('Action could not be completed')
            ->assertSee('The indent field must be a file of type: pdf.');
    }
}
