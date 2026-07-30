<?php

namespace Database\Seeders;

use App\Models\BoardColumn;
use App\Models\BoardQuarterTarget;
use App\Models\Category;
use App\Models\InsightAccount;
use App\Models\InsightContent;
use App\Models\KeyResult;
use App\Models\Label;
use App\Models\Objective;
use App\Models\Pipeline;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Quarter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data OKR Q3 2026 berdasarkan desain / screenshot SKINKU B2B Distributor Portal.
 * Juga menyertakan data pendukung (KPI, board, kartu kanban, dll).
 */
class SkinkuOkrSeeder extends Seeder
{
    public function run(): void
    {
        $year = 2026;
        $quarter = 3;

        $this->ensureUsers();
        $this->seedOkr($year, $quarter);

        $this->command?->info("Seeder OKR Q3 2026 SKINKU berhasil dijalankan!");
    }

    private function ensureUsers(): array
    {
        $usersData = [
            ['name' => 'Devrina', 'role' => 'manager'],
            ['name' => 'Bahtiar Tiar', 'role' => 'manager'],
            ['name' => 'Billy', 'role' => 'manager'],
            ['name' => 'Agatha', 'role' => 'manager'],
            ['name' => 'Freddie', 'role' => 'manager'],
            ['name' => 'Gracelyn', 'role' => 'manager'],
        ];

        $users = [];
        foreach ($usersData as $u) {
            $user = User::firstOrCreate(
                ['name' => $u['name']],
                [
                    'email' => strtolower(str_replace(' ', '', $u['name'])) . '@skinku.id',
                    'password' => Hash::make('password123'),
                    'role' => $u['role'],
                ]
            );
            $users[$u['name']] = $user->id;
        }

        return $users;
    }

    private function seedOkr(int $year, int $quarter): void
    {
        $adminId = User::where('role', 'owner')->value('id') ?? User::first()->id;
        $users = User::pluck('id', 'name')->all();

        // Priority badges matching system labels if any
        $cmoAiPriority = Label::where('name', 'CMO AI')->first() ? ['name' => 'CMO AI', 'color' => 'bg-indigo-600'] : ['name' => 'CMO AI', 'color' => 'bg-indigo-600'];
        $cfoAiPriority = Label::where('name', 'CFO AI')->first() ? ['name' => 'CFO AI', 'color' => 'bg-blue-600'] : ['name' => 'CFO AI', 'color' => 'bg-blue-600'];
        $cooAiPriority = Label::where('name', 'COO AI')->first() ? ['name' => 'COO AI', 'color' => 'bg-sky-600'] : ['name' => 'COO AI', 'color' => 'bg-sky-600'];

        // ------------------------------------------------------------------------
        // OBJECTIVE 1: (From Image 5 - Objective 1 / CMO AI)
        // Title: (Target Omzet / Jaringan Distributor & Affiliate) - Objective 1: CMO AI - Freddie
        // Note: Image 5 shows KEY RESULT 1.3 under OBJECTIVE 1 (CMO AI - Freddie)
        // ------------------------------------------------------------------------
        $obj1 = Objective::updateOrCreate(
            ['year' => $year, 'quarter' => $quarter, 'title' => 'Mencapai Target Omzet E-Commerce dan Distributor'],
            [
                'description' => 'Fokus strategi pemasaran CMO AI untuk mendorong omzet e-commerce Rp500.000.000/bulan dan membangun jaringan distributor & affiliate aktif.',
                'priority' => $cmoAiPriority,
                'omset_target' => 500000000,
                'omset_owner_id' => $users['Freddie'] ?? $adminId,
                'position' => 1,
                'created_by' => $adminId,
            ]
        );

        // KR 1.3: Membangun jaringan affiliate aktif sebanyak 1.500.
        $kr1_3 = KeyResult::updateOrCreate(
            ['objective_id' => $obj1->id, 'title' => 'Membangun jaringan affiliate aktif sebanyak 1.500.'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 1500,
                'actual_manual' => 117,
                'unit' => 'angka',
                'priority' => null,
                'position' => 3,
                'owner_id' => $users['Freddie'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr1_3->id, [
            [
                'endorse' => 'Merekrut dan melakukan onboarding affiliate dengan konten yang tepat.',
                'description' => 'Menyiapkan materi onboarding serta mengadakan sesi pelatihan live untuk affiliate baru agar siap untuk sukai dan kombinasikan konten mereka.',
                'assigned_to' => $users['Agatha'] ?? null,
                'deadline' => '2026-08-15',
                'progress' => 'progress',
            ],
            [
                'endorse' => 'Produksi materi video dan UGC untuk campaign Q3',
                'description' => 'Siapkan kebutuhan talent dan lakukan produksi video/UGC sesuai brief campaign. Serahkan aset final yang siap tayang beserta daftar revisi atau validasi yang masih diperlukan.',
                'assigned_to' => $users['Gracelyn'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
            [
                'endorse' => 'Review dan approval CMO: Mencapai Target Omzet E-Commerce sebesar Rp500.000.000 per bulan.',
                'description' => 'Tinjau hasil kerja dan risiko lintas fungsi untuk Objective ini. Berikan keputusan, koreksi, atau approval tertulis sebelum tenggat Key Result.',
                'assigned_to' => $users['Freddie'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
                'is_kr_master' => true,
            ]
        ]);


        // ------------------------------------------------------------------------
        // OBJECTIVE 2: Meningkatkan Penjualan E-Commerce dan Distributor
        // (Image 4 & Image 3) - CFO AI - Billy
        // ------------------------------------------------------------------------
        $obj2 = Objective::updateOrCreate(
            ['year' => $year, 'quarter' => $quarter, 'title' => 'Meningkatkan Penjualan E-Commerce dan Distributor'],
            [
                'description' => "Fokus untuk memastikan margin dan cashflow yang sehat sambil mencapai target omzet.\nAlasan dipilih: Menjaga keseimbangan antara penjualan e-commerce dan produksi agar tidak merusak cashflow.",
                'priority' => $cfoAiPriority,
                'omset_target' => 500000000,
                'omset_owner_id' => $users['Billy'] ?? $adminId,
                'position' => 2,
                'created_by' => $adminId,
            ]
        );

        // KR 2.1: Capai omzet e-commerce bulanan Rp500.000.000.
        $kr2_1 = KeyResult::updateOrCreate(
            ['objective_id' => $obj2->id, 'title' => 'Capai omzet e-commerce bulanan Rp500.000.000.'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 500000000,
                'actual_manual' => 102143055,
                'unit' => 'rupiah',
                'priority' => null,
                'position' => 1,
                'owner_id' => $users['Billy'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr2_1->id, [
            [
                'endorse' => 'Mengevaluasi produk yang ada untuk penjualan dan margin.',
                'description' => 'Analisis seluruh SKU untuk memastikan margin dan profitabilitas masing-masing produk serta untuk mendukung keputusan peluncuran produk baru atau promo.',
                'assigned_to' => $users['Agatha'] ?? null,
                'deadline' => '2026-08-12',
                'progress' => 'progress',
                'is_kr_master' => true,
            ]
        ]);

        // KR 2.2: Capai target 30 distributor aktif dengan omzet minimal Rp100.000.000 per bulan.
        $kr2_2 = KeyResult::updateOrCreate(
            ['objective_id' => $obj2->id, 'title' => 'Capai target 30 distributor aktif dengan omzet minimal Rp100.000.000 per bulan.'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 30,
                'actual_manual' => 0,
                'unit' => 'angka',
                'priority' => null,
                'position' => 2,
                'owner_id' => $users['Billy'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr2_2->id, [
            [
                'endorse' => 'Monitoring omzet distributor secara berkala.',
                'description' => 'Membuat dashboard monitoring distribusi untuk melihat performa masing-masing distributor agar dapat mendeteksi lebih awal jika diperlukan support.',
                'assigned_to' => $users['Bahtiar Tiar'] ?? null,
                'deadline' => '2026-08-25',
                'progress' => 'progress',
                'is_kr_master' => true,
            ]
        ]);

        // KR 2.3: Rekrut 5.000 Affiliator Aktif.
        $kr2_3 = KeyResult::updateOrCreate(
            ['objective_id' => $obj2->id, 'title' => 'Rekrut 5.000 Affiliator Aktif.'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 5000,
                'actual_manual' => 0,
                'unit' => 'angka',
                'priority' => null,
                'position' => 3,
                'owner_id' => $users['Billy'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr2_3->id, [
            [
                'endorse' => 'Jalankan dan validasi: Rekrut 5.000 Affiliator Aktif.',
                'description' => 'Turunkan Key Result ini menjadi rencana kerja, milestone, dan deliverable yang dapat diperiksa. Dokumentasikan baseline, hasil aktual, risiko, serta keputusan atau approval yang masih diperlukan.',
                'assigned_to' => $users['Billy'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
                'is_kr_master' => true,
            ],
            [
                'endorse' => 'Review dan approval CFO: Meningkatkan Penjualan E-Commerce dan Distributor',
                'description' => 'Tinjau hasil kerja dan risiko lintas fungsi untuk Objective ini. Berikan keputusan, koreksi, atau approval tertulis sebelum tenggat Key Result.',
                'assigned_to' => $users['Billy'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ]
        ]);


        // ------------------------------------------------------------------------
        // OBJECTIVE 3: Meningkatkan Omzet E-Commerce SKINSKU / SKINIKU
        // (Image 2 & Image 1) - COO AI - Devrina
        // ------------------------------------------------------------------------
        $obj3 = Objective::updateOrCreate(
            ['year' => $year, 'quarter' => $quarter, 'title' => 'Meningkatkan Omzet E-Commerce SKINKU'],
            [
                'description' => "SKINKU berfokus pada peningkatan omzet e-commerce dan distributor serta pengembangan jaringan affiliate di Q3 2026. Target omzet e-commerce sebesar Rp500.000.000 per bulan memerlukan evaluasi dari komposisi SKU existing, kontribusi Perfume dan Acne Series, serta peluncuran produk baru. Selain itu, untuk mencapai 30 distributor aktif dengan total omzet Rp9.000.000.000 per kuartal, relasi dan dukungan pada distributor sangat penting. Jaringan affiliate 5.000 orang harus diperoleh dengan memperhatikan produktivitas dan retensi serta keterlibatan aktif dari affiliator. Kesiapan produk juga perlu diperhatikan, terutama dengan tingginya permintaan terhadap produk yang sudah terbukti laku.\nAlasan dipilih: Kenaikan omzet e-commerce secara signifikan akan mendongkrak cashflow dan meningkatkan margin dalam jangka panjang.",
                'priority' => $cooAiPriority,
                'omset_target' => 500000000,
                'omset_owner_id' => $users['Devrina'] ?? $adminId,
                'position' => 3,
                'created_by' => $adminId,
            ]
        );

        // KR 3.1: Mencapai omzet e-commerce Rp500.000.000/bulan
        $kr3_1 = KeyResult::updateOrCreate(
            ['objective_id' => $obj3->id, 'title' => 'Mencapai omzet e-commerce Rp500.000.000/bulan'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 500000000,
                'actual_manual' => 0,
                'unit' => 'rupiah',
                'priority' => null,
                'position' => 1,
                'owner_id' => $users['Devrina'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr3_1->id, [
            [
                'endorse' => 'Optimalisasi produk existing',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
                'is_kr_master' => true,
            ],
            [
                'endorse' => 'Peluncuran produk baru',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
            [
                'endorse' => 'Kampanye pemasaran strategis',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
            [
                'endorse' => 'Riset dan pengembangan produk',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
            [
                'endorse' => 'Produksi dan pengujian pasar',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
        ]);

        // KR 3.2: Menambah 15 produk baru ke dalam SKU
        $kr3_2 = KeyResult::updateOrCreate(
            ['objective_id' => $obj3->id, 'title' => 'Menambah 15 produk baru ke dalam SKU'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 15,
                'actual_manual' => 0,
                'unit' => 'angka',
                'priority' => null,
                'position' => 2,
                'owner_id' => $users['Devrina'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        // KR 3.3: Meningkatkan jumlah pengulangan pemesanan sebesar 20%
        $kr3_3 = KeyResult::updateOrCreate(
            ['objective_id' => $obj3->id, 'title' => 'Meningkatkan jumlah pengulangan pemesanan sebesar 20%'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 20,
                'actual_manual' => 0,
                'unit' => 'persen',
                'priority' => null,
                'position' => 3,
                'owner_id' => $users['Devrina'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr3_3->id, [
            [
                'endorse' => 'Strategi kampanye repeat order',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
                'is_kr_master' => true,
            ],
            [
                'endorse' => 'Program loyalitas pelanggan',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
        ]);

        // KR 3.4: Mencapai 30 distributor aktif
        $kr3_4 = KeyResult::updateOrCreate(
            ['objective_id' => $obj3->id, 'title' => 'Mencapai 30 distributor aktif'],
            [
                'source' => 'manual',
                'metric' => null,
                'target' => 30,
                'actual_manual' => 0,
                'unit' => 'angka',
                'priority' => null,
                'position' => 4,
                'owner_id' => $users['Devrina'] ?? $adminId,
                'created_by' => $adminId,
            ]
        );

        $this->createCardsForKr($kr3_4->id, [
            [
                'endorse' => 'Reaktivasi distributor lama',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Bahtiar Tiar'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
            [
                'endorse' => 'Program onboarding',
                'description' => 'Jalankan workstream ini berdasarkan diagnosis panel COO dan bukti sistem. Serahkan hasil terukur, catatan validasi, risiko, serta keputusan yang masih diperlukan.',
                'assigned_to' => $users['Agatha'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
            ],
            [
                'endorse' => 'Review dan approval COO: Meningkatkan Omzet E-Commerce SKINKU',
                'description' => 'Tinjau hasil kerja dan risiko lintas fungsi untuk Objective ini. Berikan keputusan, koreksi, atau approval tertulis sebelum tenggat Key Result.',
                'assigned_to' => $users['Devrina'] ?? null,
                'deadline' => '2026-09-30',
                'progress' => 'todo',
                'is_kr_master' => true,
            ],
        ]);
    }

    private function createCardsForKr(int $krId, array $cards): void
    {
        $adminId = User::where('role', 'owner')->value('id') ?? User::first()->id;

        foreach ($cards as $idx => $c) {
            Pipeline::updateOrCreate(
                [
                    'key_result_id' => $krId,
                    'endorse' => $c['endorse'],
                ],
                [
                    'category' => 'skinku_management',
                    'account' => 'fk',
                    'payment_status' => 'belum',
                    'progress' => $c['progress'] ?? 'todo',
                    'description' => $c['description'] ?? null,
                    'assigned_to' => $c['assigned_to'] ?? null,
                    'deadline' => $c['deadline'] ?? null,
                    'is_kr_master' => $c['is_kr_master'] ?? false,
                    'created_by' => $adminId,
                    'position' => $idx,
                ]
            );
        }
    }
}
