<?php

namespace Tests\Feature;

use Modules\Core\Models\ApprovalRule;
use Modules\Pengadaan\Models\Belanja;
use Tests\TestCase;

/**
 * Pra-syarat aktivasi G1-01: whitelist field/operator ApprovalRule (dipakai UI)
 * harus konsisten dengan engine terpicu(), mencegah rule silent fail-open akibat
 * salah-ketik field/operator. Murni cek konfigurasi (tanpa DB).
 */
class ApprovalRuleConfigTest extends TestCase
{
    public function test_setiap_tipe_approvable_punya_field_options(): void
    {
        foreach (array_keys(ApprovalRule::APPROVABLE) as $type) {
            $this->assertArrayHasKey($type, ApprovalRule::FIELD_OPTIONS, "FIELD_OPTIONS wajib mencakup {$type}");
            $this->assertNotEmpty(ApprovalRule::FIELD_OPTIONS[$type]);
        }
    }

    public function test_field_valid_helper(): void
    {
        $bel = Belanja::class;
        $this->assertTrue(ApprovalRule::fieldValid($bel, 'total'));
        $this->assertFalse(ApprovalRule::fieldValid($bel, 'totl'));     // typo → ditolak
        $this->assertFalse(ApprovalRule::fieldValid($bel, 'nominal'));  // field tipe lain
        $this->assertTrue(ApprovalRule::fieldValid($bel, null));        // kosong = selalu
    }

    public function test_operator_options_cocok_dengan_engine(): void
    {
        $ops = array_keys(ApprovalRule::OPERATORS);
        sort($ops);
        $expected = ['<', '<=', '=', '>', '>='];
        sort($expected);
        $this->assertSame($expected, $ops, 'OPERATORS harus persis = operator yang didukung terpicu()');

        // Fungsional: operator yang ada di whitelist benar-benar bekerja di engine.
        $rule = new ApprovalRule(['field' => 'total', 'operator' => '>=', 'nilai' => 1000]);
        $this->assertTrue($rule->terpicu(new Belanja(['total' => 1000])));
        $this->assertFalse($rule->terpicu(new Belanja(['total' => 999])));
    }
}
