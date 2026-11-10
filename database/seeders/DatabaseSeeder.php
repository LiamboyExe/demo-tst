<?php

namespace Database\Seeders;

use App\Enums\NfcCardStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Hood;
use App\Models\Member;
use App\Models\NfcCard;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with the Temple Street demo data.
     */
    public function run(): void
    {
        // --- Territory: Tandang Sora ---
        $tandangSora = Branch::query()->updateOrCreate(
            ['name' => 'Tandang Sora'],
            [
                'location' => 'Tandang Sora Ave, Quezon City',
                'manager' => 'Rodrigo "Digoy" Dela Cruz',
                'phone' => '(02) 8555-1234',
                'email' => 'tandangsora@templestreet.local',
            ],
        );

        // --- Territory: Fairview ---
        $fairview = Branch::query()->updateOrCreate(
            ['name' => 'Fairview'],
            [
                'location' => 'Commonwealth Ave / Fairview, Quezon City',
                'manager' => 'Eduardo "Tata Eddie" Morales',
                'phone' => '(02) 8555-5678',
                'email' => 'fairview@templestreet.local',
            ],
        );

        // --- System & Territory Admin Accounts ---
        $this->seedUser('admin', 'Main Administrator', 'admin@templestreet.local', 'admin123', UserRole::Main, null);
        $this->seedUser('ts_admin', 'Tandang Sora Admin', 'ts_admin@templestreet.local', 'ts123456', UserRole::Local, $tandangSora);
        $this->seedUser('fv_admin', 'Fairview Admin', 'fv_admin@templestreet.local', 'fv123456', UserRole::Local, $fairview);

        // --- Seed Hoods and Members for Tandang Sora (11 hoods, 10 members each) ---
        $this->seedTerritoryHoodsAndMembers($tandangSora, $this->tandangSoraMemberData());

        // --- Seed Hoods and Members for Fairview (11 hoods, 10 members each) ---
        $this->seedTerritoryHoodsAndMembers($fairview, $this->fairviewMemberData());

        // Seed a few NFC cards for early members
        $nfcCards = [
            'cl-m1-santos-juan' => ['NFC-A1B2C3D4', NfcCardStatus::Active, '2023-01-20', 'Primary card'],
            'cl-m2-reyes-maria' => ['NFC-E5F6A7B8', NfcCardStatus::Active, '2023-02-25', 'Primary card'],
            'tv-m1-mendoza-carlo' => ['NFC-C9D0E1F2', NfcCardStatus::Active, '2023-03-15', 'Primary card'],
            'tv-m2-garcia-ana' => ['NFC-G3H4I5J6', NfcCardStatus::Inactive, '2023-04-10', 'Member deactivated'],
            'pl-m1-flores-pedro' => ['NFC-K7L8M9N0', NfcCardStatus::Active, '2023-05-22', 'Primary card'],
            'nl-m1-cortez-paolo' => ['NFC-F1V2C3D4', NfcCardStatus::Active, '2023-06-15', 'Fairview Primary card'],
            'vd-m1-esguerra-rafael' => ['NFC-F5V6A7B8', NfcCardStatus::Active, '2023-07-20', 'Fairview Primary card'],
        ];

        foreach ($nfcCards as $slug => [$uid, $status, $issued, $notes]) {
            $member = Member::query()->where('profile_slug', $slug)->first();

            if ($member === null) {
                continue;
            }

            NfcCard::query()->updateOrCreate(
                ['member_id' => $member->id],
                [
                    'card_uid' => $uid,
                    'profile_url' => route('profile.show', $member),
                    'card_status' => $status,
                    'issued_date' => $issued,
                    'notes' => $notes,
                ],
            );
        }

        // --- Demo Member Account (Juan Santos) ---
        // Shows the member login flow: sign in on the NFC profile, then the
        // forced temporary-password change on first login.
        $juan = Member::query()->where('profile_slug', 'cl-m1-santos-juan')->first();

        if ($juan !== null && $juan->user()->doesntExist()) {
            $juan->user()->create([
                'name' => $juan->full_name,
                'username' => 'juan',
                'email' => $juan->email,
                'password' => 'juan123456',
                'role' => UserRole::Member,
                'activated_at' => now(),
                'email_verified_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $membersByHood
     */
    private function seedTerritoryHoodsAndMembers(Branch $territory, array $membersByHood): void
    {
        foreach ($membersByHood as $hoodName => $members) {
            $hood = Hood::query()->updateOrCreate(
                ['branch_id' => $territory->id, 'name' => $hoodName],
            );

            foreach ($members as $row) {
                $member = Member::query()->updateOrCreate(
                    ['profile_slug' => $row['slug']],
                    [
                        'branch_id' => $territory->id,
                        'hood_id' => $hood->id,
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'email' => $row['email'],
                        'phone' => $row['phone'],
                        'address' => $row['address'],
                        'membership_type' => $row['membership_type'],
                        'status' => $row['status'],
                        'join_date' => $row['join_date'],
                        'approved' => $row['approved'],
                    ],
                );

                if ($row['slug'] !== 'cl-m1-santos-juan' && $member->user()->doesntExist()) {
                    $member->issueTemporaryCredentials();
                }
            }
        }
    }

    private function seedUser(
        string $username,
        string $name,
        string $email,
        string $password,
        UserRole $role,
        ?Branch $branch,
    ): void {
        $user = User::query()->updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'branch_id' => $branch?->id,
                'email_verified_at' => now(),
            ],
        );

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function tandangSoraMemberData(): array
    {
        return [
            'Calle Locos' => [
                ['first_name' => 'Juan', 'last_name' => 'Santos', 'email' => 'juan.santos@mail.demo', 'phone' => '09171000001', 'address' => '1 Calle Locos St, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2021-03-10', 'slug' => 'cl-m1-santos-juan', 'approved' => true],
                ['first_name' => 'Maria', 'last_name' => 'Reyes', 'email' => 'maria.reyes@mail.demo', 'phone' => '09171000002', 'address' => '2 Calle Locos St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-04-15', 'slug' => 'cl-m2-reyes-maria', 'approved' => true],
                ['first_name' => 'Ramon', 'last_name' => 'Bautista', 'email' => 'ramon.bautista@mail.demo', 'phone' => '09171000003', 'address' => '3 Calle Locos St, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2021-05-20', 'slug' => 'cl-m3-bautista-ramon', 'approved' => true],
                ['first_name' => 'Luisa', 'last_name' => 'Cruz', 'email' => 'luisa.cruz@mail.demo', 'phone' => '09171000004', 'address' => '4 Calle Locos St, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2021-06-11', 'slug' => 'cl-m4-cruz-luisa', 'approved' => true],
                ['first_name' => 'Andres', 'last_name' => 'Villanueva', 'email' => 'andres.v@mail.demo', 'phone' => '09171000005', 'address' => '5 Calle Locos St, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2021-07-09', 'slug' => 'cl-m5-villanueva-andres', 'approved' => true],
                ['first_name' => 'Cynthia', 'last_name' => 'Navarro', 'email' => 'cynthia.n@mail.demo', 'phone' => '09171000006', 'address' => '6 Calle Locos St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-08-22', 'slug' => 'cl-m6-navarro-cynthia', 'approved' => true],
                ['first_name' => 'Ernesto', 'last_name' => 'Torres', 'email' => 'ernesto.t@mail.demo', 'phone' => '09171000007', 'address' => '7 Calle Locos St, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2021-09-30', 'slug' => 'cl-m7-torres-ernesto', 'approved' => true],
                ['first_name' => 'Felicia', 'last_name' => 'Gomez', 'email' => 'felicia.g@mail.demo', 'phone' => '09171000008', 'address' => '8 Calle Locos St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2021-10-18', 'slug' => 'cl-m8-gomez-felicia', 'approved' => true],
                ['first_name' => 'Gregorio', 'last_name' => 'Lim', 'email' => 'gregorio.lim@mail.demo', 'phone' => '09171000009', 'address' => '9 Calle Locos St, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2022-01-05', 'slug' => 'cl-m9-lim-gregorio', 'approved' => true],
                ['first_name' => 'Helena', 'last_name' => 'Dizon', 'email' => 'helena.dizon@mail.demo', 'phone' => '09171000010', 'address' => '10 Calle Locos St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2022-02-14', 'slug' => 'cl-m10-dizon-helena', 'approved' => false],
            ],

            'Traviezo' => [
                ['first_name' => 'Carlo', 'last_name' => 'Mendoza', 'email' => 'carlo.mendoza@mail.demo', 'phone' => '09172000001', 'address' => '1 Traviezo Blk, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2020-06-15', 'slug' => 'tv-m1-mendoza-carlo', 'approved' => true],
                ['first_name' => 'Ana', 'last_name' => 'Garcia', 'email' => 'ana.garcia@mail.demo', 'phone' => '09172000002', 'address' => '2 Traviezo Blk, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-07-20', 'slug' => 'tv-m2-garcia-ana', 'approved' => true],
                ['first_name' => 'Benito', 'last_name' => 'Ramos', 'email' => 'benito.ramos@mail.demo', 'phone' => '09172000003', 'address' => '3 Traviezo Blk, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2020-08-11', 'slug' => 'tv-m3-ramos-benito', 'approved' => true],
                ['first_name' => 'Diana', 'last_name' => 'Castillo', 'email' => 'diana.c@mail.demo', 'phone' => '09172000004', 'address' => '4 Traviezo Blk, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-09-05', 'slug' => 'tv-m4-castillo-diana', 'approved' => true],
                ['first_name' => 'Eduardo', 'last_name' => 'Alvarez', 'email' => 'eduardo.a@mail.demo', 'phone' => '09172000005', 'address' => '5 Traviezo Blk, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2020-10-18', 'slug' => 'tv-m5-alvarez-eduardo', 'approved' => true],
                ['first_name' => 'Flora', 'last_name' => 'Perez', 'email' => 'flora.perez@mail.demo', 'phone' => '09172000006', 'address' => '6 Traviezo Blk, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-11-25', 'slug' => 'tv-m6-perez-flora', 'approved' => true],
                ['first_name' => 'Gerry', 'last_name' => 'Aquino', 'email' => 'gerry.aquino@mail.demo', 'phone' => '09172000007', 'address' => '7 Traviezo Blk, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2020-12-14', 'slug' => 'tv-m7-aquino-gerry', 'approved' => true],
                ['first_name' => 'Hazel', 'last_name' => 'Morales', 'email' => 'hazel.m@mail.demo', 'phone' => '09172000008', 'address' => '8 Traviezo Blk, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2021-01-22', 'slug' => 'tv-m8-morales-hazel', 'approved' => true],
                ['first_name' => 'Ignacio', 'last_name' => 'Fernandez', 'email' => 'ignacio.f@mail.demo', 'phone' => '09172000009', 'address' => '9 Traviezo Blk, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2021-02-17', 'slug' => 'tv-m9-fernandez-ignacio', 'approved' => true],
                ['first_name' => 'Joyce', 'last_name' => 'Mercado', 'email' => 'joyce.mercado@mail.demo', 'phone' => '09172000010', 'address' => '10 Traviezo Blk, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2021-03-30', 'slug' => 'tv-m10-mercado-joyce', 'approved' => false],
            ],

            'Pantranco Locos' => [
                ['first_name' => 'Pedro', 'last_name' => 'Flores', 'email' => 'pedro.flores@mail.demo', 'phone' => '09173000001', 'address' => '1 Pantranco Ave, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2019-01-10', 'slug' => 'pl-m1-flores-pedro', 'approved' => true],
                ['first_name' => 'Rosa', 'last_name' => 'Gutierrez', 'email' => 'rosa.g@mail.demo', 'phone' => '09173000002', 'address' => '2 Pantranco Ave, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-02-14', 'slug' => 'pl-m2-gutierrez-rosa', 'approved' => true],
                ['first_name' => 'Samuel', 'last_name' => 'Tan', 'email' => 'samuel.tan@mail.demo', 'phone' => '09173000003', 'address' => '3 Pantranco Ave, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2019-03-21', 'slug' => 'pl-m3-tan-samuel', 'approved' => true],
                ['first_name' => 'Teresa', 'last_name' => 'Ong', 'email' => 'teresa.ong@mail.demo', 'phone' => '09173000004', 'address' => '4 Pantranco Ave, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-04-18', 'slug' => 'pl-m4-ong-teresa', 'approved' => true],
                ['first_name' => 'Ulysses', 'last_name' => 'Chua', 'email' => 'ulysses.chua@mail.demo', 'phone' => '09173000005', 'address' => '5 Pantranco Ave, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2019-05-25', 'slug' => 'pl-m5-chua-ulysses', 'approved' => true],
                ['first_name' => 'Victoria', 'last_name' => 'Sy', 'email' => 'victoria.sy@mail.demo', 'phone' => '09173000006', 'address' => '6 Pantranco Ave, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-06-30', 'slug' => 'pl-m6-sy-victoria', 'approved' => true],
                ['first_name' => 'Walter', 'last_name' => 'Go', 'email' => 'walter.go@mail.demo', 'phone' => '09173000007', 'address' => '7 Pantranco Ave, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2019-07-15', 'slug' => 'pl-m7-go-walter', 'approved' => true],
                ['first_name' => 'Ximena', 'last_name' => 'Co', 'email' => 'ximena.co@mail.demo', 'phone' => '09173000008', 'address' => '8 Pantranco Ave, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2019-08-20', 'slug' => 'pl-m8-co-ximena', 'approved' => true],
                ['first_name' => 'Yolanda', 'last_name' => 'Yap', 'email' => 'yolanda.yap@mail.demo', 'phone' => '09173000009', 'address' => '9 Pantranco Ave, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2019-09-12', 'slug' => 'pl-m9-yap-yolanda', 'approved' => true],
                ['first_name' => 'Zachary', 'last_name' => 'Dee', 'email' => 'zachary.dee@mail.demo', 'phone' => '09173000010', 'address' => '10 Pantranco Ave, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Pending', 'join_date' => '2019-10-05', 'slug' => 'pl-m10-dee-zachary', 'approved' => false],
            ],

            'Old Black' => [
                ['first_name' => 'Arturo', 'last_name' => 'Lopez', 'email' => 'arturo.lopez@mail.demo', 'phone' => '09174000001', 'address' => '1 Old Black Way, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2018-05-12', 'slug' => 'ob-m1-lopez-arturo', 'approved' => true],
                ['first_name' => 'Bernadette', 'last_name' => 'Diaz', 'email' => 'bernadette.d@mail.demo', 'phone' => '09174000002', 'address' => '2 Old Black Way, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-06-19', 'slug' => 'ob-m2-diaz-bernadette', 'approved' => true],
                ['first_name' => 'Crispin', 'last_name' => 'Castro', 'email' => 'crispin.c@mail.demo', 'phone' => '09174000003', 'address' => '3 Old Black Way, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2018-07-23', 'slug' => 'ob-m3-castro-crispin', 'approved' => true],
                ['first_name' => 'Dolores', 'last_name' => 'Romero', 'email' => 'dolores.r@mail.demo', 'phone' => '09174000004', 'address' => '4 Old Black Way, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-08-15', 'slug' => 'ob-m4-romero-dolores', 'approved' => true],
                ['first_name' => 'Esteban', 'last_name' => 'Herrera', 'email' => 'esteban.h@mail.demo', 'phone' => '09174000005', 'address' => '5 Old Black Way, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2018-09-08', 'slug' => 'ob-m5-herrera-esteban', 'approved' => true],
                ['first_name' => 'Francisca', 'last_name' => 'Medina', 'email' => 'francisca.m@mail.demo', 'phone' => '09174000006', 'address' => '6 Old Black Way, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-10-30', 'slug' => 'ob-m6-medina-francisca', 'approved' => true],
                ['first_name' => 'Guillermo', 'last_name' => 'Aguilar', 'email' => 'guillermo.a@mail.demo', 'phone' => '09174000007', 'address' => '7 Old Black Way, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2018-11-14', 'slug' => 'ob-m7-aguilar-guillermo', 'approved' => true],
                ['first_name' => 'Hilda', 'last_name' => 'Vega', 'email' => 'hilda.vega@mail.demo', 'phone' => '09174000008', 'address' => '8 Old Black Way, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2018-12-05', 'slug' => 'ob-m8-vega-hilda', 'approved' => true],
                ['first_name' => 'Isagani', 'last_name' => 'Rios', 'email' => 'isagani.rios@mail.demo', 'phone' => '09174000009', 'address' => '9 Old Black Way, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2019-01-20', 'slug' => 'ob-m9-rios-isagani', 'approved' => true],
                ['first_name' => 'Julita', 'last_name' => 'Serrano', 'email' => 'julita.s@mail.demo', 'phone' => '09174000010', 'address' => '10 Old Black Way, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2019-02-18', 'slug' => 'ob-m10-serrano-julita', 'approved' => false],
            ],

            'Les Indios' => [
                ['first_name' => 'Kiko', 'last_name' => 'Navarrete', 'email' => 'kiko.n@mail.demo', 'phone' => '09175000001', 'address' => '1 Les Indios Alley, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2017-03-15', 'slug' => 'li-m1-navarrete-kiko', 'approved' => true],
                ['first_name' => 'Lourdes', 'last_name' => 'Velasco', 'email' => 'lourdes.v@mail.demo', 'phone' => '09175000002', 'address' => '2 Les Indios Alley, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-04-20', 'slug' => 'li-m2-velasco-lourdes', 'approved' => true],
                ['first_name' => 'Manuel', 'last_name' => 'Miranda', 'email' => 'manuel.m@mail.demo', 'phone' => '09175000003', 'address' => '3 Les Indios Alley, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2017-05-25', 'slug' => 'li-m3-miranda-manuel', 'approved' => true],
                ['first_name' => 'Nora', 'last_name' => 'Pascual', 'email' => 'nora.pascual@mail.demo', 'phone' => '09175000004', 'address' => '4 Les Indios Alley, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-06-12', 'slug' => 'li-m4-pascual-nora', 'approved' => true],
                ['first_name' => 'Orlando', 'last_name' => 'Delos Santos', 'email' => 'orlando.ds@mail.demo', 'phone' => '09175000005', 'address' => '5 Les Indios Alley, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2017-07-18', 'slug' => 'li-m5-delossantos-orlando', 'approved' => true],
                ['first_name' => 'Pilar', 'last_name' => 'Soriano', 'email' => 'pilar.soriano@mail.demo', 'phone' => '09175000006', 'address' => '6 Les Indios Alley, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-08-30', 'slug' => 'li-m6-soriano-pilar', 'approved' => true],
                ['first_name' => 'Quirino', 'last_name' => 'Tolentino', 'email' => 'quirino.t@mail.demo', 'phone' => '09175000007', 'address' => '7 Les Indios Alley, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2017-09-14', 'slug' => 'li-m7-tolentino-quirino', 'approved' => true],
                ['first_name' => 'Remedios', 'last_name' => 'Corpuz', 'email' => 'remedios.c@mail.demo', 'phone' => '09175000008', 'address' => '8 Les Indios Alley, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2017-10-09', 'slug' => 'li-m8-corpuz-remedios', 'approved' => true],
                ['first_name' => 'Salvador', 'last_name' => 'Villareal', 'email' => 'salvador.v@mail.demo', 'phone' => '09175000009', 'address' => '9 Les Indios Alley, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2017-11-22', 'slug' => 'li-m9-villareal-salvador', 'approved' => true],
                ['first_name' => 'Trinidad', 'last_name' => 'Valdez', 'email' => 'trinidad.v@mail.demo', 'phone' => '09175000010', 'address' => '10 Les Indios Alley, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2017-12-15', 'slug' => 'li-m10-valdez-trinidad', 'approved' => false],
            ],

            'Crazyland' => [
                ['first_name' => 'Aurelio', 'last_name' => 'Suarez', 'email' => 'aurelio.suarez@mail.demo', 'phone' => '09176000001', 'address' => '1 Crazyland Blvd, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2020-01-15', 'slug' => 'cz-m1-suarez-aurelio', 'approved' => true],
                ['first_name' => 'Beatriz', 'last_name' => 'Esguerra', 'email' => 'beatriz.e@mail.demo', 'phone' => '09176000002', 'address' => '2 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-02-20', 'slug' => 'cz-m2-esguerra-beatriz', 'approved' => true],
                ['first_name' => 'Cesar', 'last_name' => 'Fajardo', 'email' => 'cesar.fajardo@mail.demo', 'phone' => '09176000003', 'address' => '3 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2020-03-25', 'slug' => 'cz-m3-fajardo-cesar', 'approved' => true],
                ['first_name' => 'Digna', 'last_name' => 'Ocampo', 'email' => 'digna.ocampo@mail.demo', 'phone' => '09176000004', 'address' => '4 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-04-10', 'slug' => 'cz-m4-ocampo-digna', 'approved' => true],
                ['first_name' => 'Eugenio', 'last_name' => 'Salazar', 'email' => 'eugenio.s@mail.demo', 'phone' => '09176000005', 'address' => '5 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2020-05-18', 'slug' => 'cz-m5-salazar-eugenio', 'approved' => true],
                ['first_name' => 'Fortunata', 'last_name' => 'Laurel', 'email' => 'fortunata.l@mail.demo', 'phone' => '09176000006', 'address' => '6 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-06-22', 'slug' => 'cz-m6-laurel-fortunata', 'approved' => true],
                ['first_name' => 'Gil', 'last_name' => 'Manalo', 'email' => 'gil.manalo@mail.demo', 'phone' => '09176000007', 'address' => '7 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2020-07-30', 'slug' => 'cz-m7-manalo-gil', 'approved' => true],
                ['first_name' => 'Herminia', 'last_name' => 'Panganiban', 'email' => 'herminia.p@mail.demo', 'phone' => '09176000008', 'address' => '8 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2020-08-14', 'slug' => 'cz-m8-panganiban-herminia', 'approved' => true],
                ['first_name' => 'Ildefonso', 'last_name' => 'Roxas', 'email' => 'ildefonso.r@mail.demo', 'phone' => '09176000009', 'address' => '9 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2020-09-09', 'slug' => 'cz-m9-roxas-ildefonso', 'approved' => true],
                ['first_name' => 'Jacinta', 'last_name' => 'Quizon', 'email' => 'jacinta.q@mail.demo', 'phone' => '09176000010', 'address' => '10 Crazyland Blvd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2020-10-18', 'slug' => 'cz-m10-quizon-jacinta', 'approved' => false],
            ],

            'Dreamtown' => [
                ['first_name' => 'Anacleto', 'last_name' => 'Trinidad', 'email' => 'anacleto.t@mail.demo', 'phone' => '09177000001', 'address' => '1 Dreamtown Rd, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2021-02-14', 'slug' => 'dt-m1-trinidad-anacleto', 'approved' => true],
                ['first_name' => 'Bonifacia', 'last_name' => 'Evangelista', 'email' => 'bonifacia.e@mail.demo', 'phone' => '09177000002', 'address' => '2 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-03-20', 'slug' => 'dt-m2-evangelista-bonifacia', 'approved' => true],
                ['first_name' => 'Cornelio', 'last_name' => 'Legaspi', 'email' => 'cornelio.l@mail.demo', 'phone' => '09177000003', 'address' => '3 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2021-04-15', 'slug' => 'dt-m3-legaspi-cornelio', 'approved' => true],
                ['first_name' => 'Domitila', 'last_name' => 'Pineda', 'email' => 'domitila.p@mail.demo', 'phone' => '09177000004', 'address' => '4 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-05-18', 'slug' => 'dt-m4-pineda-domitila', 'approved' => true],
                ['first_name' => 'Epifanio', 'last_name' => 'Santiago', 'email' => 'epifanio.s@mail.demo', 'phone' => '09177000005', 'address' => '5 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2021-06-22', 'slug' => 'dt-m5-santiago-epifanio', 'approved' => true],
                ['first_name' => 'Felicidad', 'last_name' => 'Mallari', 'email' => 'felicidad.m@mail.demo', 'phone' => '09177000006', 'address' => '6 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-07-30', 'slug' => 'dt-m6-mallari-felicidad', 'approved' => true],
                ['first_name' => 'Gaudioso', 'last_name' => 'David', 'email' => 'gaudioso.d@mail.demo', 'phone' => '09177000007', 'address' => '7 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2021-08-14', 'slug' => 'dt-m7-david-gaudioso', 'approved' => true],
                ['first_name' => 'Honorata', 'last_name' => 'Samson', 'email' => 'honorata.s@mail.demo', 'phone' => '09177000008', 'address' => '8 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2021-09-09', 'slug' => 'dt-m8-samson-honorata', 'approved' => true],
                ['first_name' => 'Inocencio', 'last_name' => 'Galang', 'email' => 'inocencio.g@mail.demo', 'phone' => '09177000009', 'address' => '9 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2021-10-18', 'slug' => 'dt-m9-galang-inocencio', 'approved' => true],
                ['first_name' => 'Justina', 'last_name' => 'Manansala', 'email' => 'justina.m@mail.demo', 'phone' => '09177000010', 'address' => '10 Dreamtown Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2021-11-25', 'slug' => 'dt-m10-manansala-justina', 'approved' => false],
            ],

            'San Isidro Locos' => [
                ['first_name' => 'Agapito', 'last_name' => 'Yumul', 'email' => 'agapito.yumul@mail.demo', 'phone' => '09178000001', 'address' => '1 San Isidro St, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2020-05-10', 'slug' => 'si-m1-yumul-agapito', 'approved' => true],
                ['first_name' => 'Brígida', 'last_name' => 'Capulong', 'email' => 'brigida.c@mail.demo', 'phone' => '09178000002', 'address' => '2 San Isidro St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-06-18', 'slug' => 'si-m2-capulong-brigida', 'approved' => true],
                ['first_name' => 'Crisostomo', 'last_name' => 'Panlilio', 'email' => 'crisostomo.p@mail.demo', 'phone' => '09178000003', 'address' => '3 San Isidro St, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2020-07-22', 'slug' => 'si-m3-panlilio-crisostomo', 'approved' => true],
                ['first_name' => 'Dorotea', 'last_name' => 'Datu', 'email' => 'dorotea.datu@mail.demo', 'phone' => '09178000004', 'address' => '4 San Isidro St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-08-30', 'slug' => 'si-m4-datu-dorotea', 'approved' => true],
                ['first_name' => 'Emeterio', 'last_name' => 'Henson', 'email' => 'emeterio.h@mail.demo', 'phone' => '09178000005', 'address' => '5 San Isidro St, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2020-09-15', 'slug' => 'si-m5-henson-emeterio', 'approved' => true],
                ['first_name' => 'Filomena', 'last_name' => 'Solis', 'email' => 'filomena.solis@mail.demo', 'phone' => '09178000006', 'address' => '6 San Isidro St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-10-12', 'slug' => 'si-m6-solis-filomena', 'approved' => true],
                ['first_name' => 'Geronimo', 'last_name' => 'Landayan', 'email' => 'geronimo.l@mail.demo', 'phone' => '09178000007', 'address' => '7 San Isidro St, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2020-11-20', 'slug' => 'si-m7-landayan-geronimo', 'approved' => true],
                ['first_name' => 'Hermogena', 'last_name' => 'Tiamzon', 'email' => 'hermogena.t@mail.demo', 'phone' => '09178000008', 'address' => '8 San Isidro St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2020-12-05', 'slug' => 'si-m8-tiamzon-hermogena', 'approved' => true],
                ['first_name' => 'Isidoro', 'last_name' => 'Simbulan', 'email' => 'isidoro.s@mail.demo', 'phone' => '09178000009', 'address' => '9 San Isidro St, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2021-01-14', 'slug' => 'si-m9-simbulan-isidoro', 'approved' => true],
                ['first_name' => 'Juana', 'last_name' => 'Cajucom', 'email' => 'juana.cajucom@mail.demo', 'phone' => '09178000010', 'address' => '10 San Isidro St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2021-02-28', 'slug' => 'si-m10-cajucom-juana', 'approved' => false],
            ],

            'Los Compadres' => [
                ['first_name' => 'Anselmo', 'last_name' => 'Guinto', 'email' => 'anselmo.guinto@mail.demo', 'phone' => '09179000001', 'address' => '1 Los Compadres Dr, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2016-08-10', 'slug' => 'lc-m1-guinto-anselmo', 'approved' => true],
                ['first_name' => 'Blanca', 'last_name' => 'Ronquillo', 'email' => 'blanca.r@mail.demo', 'phone' => '09179000002', 'address' => '2 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2016-09-15', 'slug' => 'lc-m2-ronquillo-blanca', 'approved' => true],
                ['first_name' => 'Cirilo', 'last_name' => 'Paras', 'email' => 'cirilo.paras@mail.demo', 'phone' => '09179000003', 'address' => '3 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2016-10-20', 'slug' => 'lc-m3-paras-cirilo', 'approved' => true],
                ['first_name' => 'Deogracias', 'last_name' => 'Policarpio', 'email' => 'deogracias.p@mail.demo', 'phone' => '09179000004', 'address' => '4 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2016-11-12', 'slug' => 'lc-m4-policarpio-deogracias', 'approved' => true],
                ['first_name' => 'Esperanza', 'last_name' => 'Gatus', 'email' => 'esperanza.g@mail.demo', 'phone' => '09179000005', 'address' => '5 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2016-12-18', 'slug' => 'lc-m5-gatus-esperanza', 'approved' => true],
                ['first_name' => 'Fausto', 'last_name' => 'Balagtas', 'email' => 'fausto.b@mail.demo', 'phone' => '09179000006', 'address' => '6 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2017-01-25', 'slug' => 'lc-m6-balagtas-fausto', 'approved' => true],
                ['first_name' => 'Gregoria', 'last_name' => 'Alcantara', 'email' => 'gregoria.alc@mail.demo', 'phone' => '09179000007', 'address' => '7 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-02-14', 'slug' => 'lc-m7-alcantara-gregoria', 'approved' => true],
                ['first_name' => 'Honesto', 'last_name' => 'Beltran', 'email' => 'honesto.b@mail.demo', 'phone' => '09179000008', 'address' => '8 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Inactive', 'join_date' => '2017-03-22', 'slug' => 'lc-m8-beltran-honesto', 'approved' => true],
                ['first_name' => 'Iluminada', 'last_name' => 'Cortez', 'email' => 'iluminada.c@mail.demo', 'phone' => '09179000009', 'address' => '9 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-04-18', 'slug' => 'lc-m9-cortez-iluminada', 'approved' => true],
                ['first_name' => 'Justo', 'last_name' => 'Dumlao', 'email' => 'justo.dumlao@mail.demo', 'phone' => '09179000010', 'address' => '10 Los Compadres Dr, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Pending', 'join_date' => '2017-05-30', 'slug' => 'lc-m10-dumlao-justo', 'approved' => false],
            ],

            'Konvict' => [
                ['first_name' => 'Abelardo', 'last_name' => 'Andaya', 'email' => 'abelardo.and@mail.demo', 'phone' => '09180000001', 'address' => '1 Konvict St, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2015-07-12', 'slug' => 'kv-m1-andaya-abelardo', 'approved' => true],
                ['first_name' => 'Basilisa', 'last_name' => 'Antiporda', 'email' => 'basilisa.an@mail.demo', 'phone' => '09180000002', 'address' => '2 Konvict St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2015-08-20', 'slug' => 'kv-m2-antiporda-basilisa', 'approved' => true],
                ['first_name' => 'Calixto', 'last_name' => 'Apostol', 'email' => 'calixto.ap@mail.demo', 'phone' => '09180000003', 'address' => '3 Konvict St, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2015-09-28', 'slug' => 'kv-m3-apostol-calixto', 'approved' => true],
                ['first_name' => 'Dolores', 'last_name' => 'Arevalo', 'email' => 'dolores.are@mail.demo', 'phone' => '09180000004', 'address' => '4 Konvict St, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2015-10-15', 'slug' => 'kv-m4-arevalo-dolores', 'approved' => true],
                ['first_name' => 'Eliseo', 'last_name' => 'Argana', 'email' => 'eliseo.arg@mail.demo', 'phone' => '09180000005', 'address' => '5 Konvict St, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2016-01-08', 'slug' => 'kv-m5-argana-eliseo', 'approved' => true],
                ['first_name' => 'Francisca', 'last_name' => 'Arroyo', 'email' => 'francisca.arr@mail.demo', 'phone' => '09180000006', 'address' => '6 Konvict St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2016-02-14', 'slug' => 'kv-m6-arroyo-francisca', 'approved' => true],
                ['first_name' => 'Gaudencio', 'last_name' => 'Aspiras', 'email' => 'gaudencio.asp@mail.demo', 'phone' => '09180000007', 'address' => '7 Konvict St, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2016-03-21', 'slug' => 'kv-m7-aspiras-gaudencio', 'approved' => true],
                ['first_name' => 'Henedina', 'last_name' => 'Austria', 'email' => 'henedina.aus@mail.demo', 'phone' => '09180000008', 'address' => '8 Konvict St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2016-04-18', 'slug' => 'kv-m8-austria-henedina', 'approved' => true],
                ['first_name' => 'Ireneo', 'last_name' => 'Avecilla', 'email' => 'ireneo.av@mail.demo', 'phone' => '09180000009', 'address' => '9 Konvict St, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2016-05-25', 'slug' => 'kv-m9-avecilla-ireneo', 'approved' => true],
                ['first_name' => 'Josefina', 'last_name' => 'Azurin', 'email' => 'josefina.az@mail.demo', 'phone' => '09180000010', 'address' => '10 Konvict St, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2016-06-30', 'slug' => 'kv-m10-azurin-josefina', 'approved' => false],
            ],

            'Trespuntos' => [
                ['first_name' => 'Abundio', 'last_name' => 'Bagtas', 'email' => 'abundio.bag@mail.demo', 'phone' => '09181000001', 'address' => '1 Trespuntos Rd, Tandang Sora', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2014-02-14', 'slug' => 'tp-m1-bagtas-abundio', 'approved' => true],
                ['first_name' => 'Bibiana', 'last_name' => 'Balbin', 'email' => 'bibiana.bal@mail.demo', 'phone' => '09181000002', 'address' => '2 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2014-03-22', 'slug' => 'tp-m2-balbin-bibiana', 'approved' => true],
                ['first_name' => 'Candido', 'last_name' => 'Baranda', 'email' => 'candido.bar@mail.demo', 'phone' => '09181000003', 'address' => '3 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2014-04-30', 'slug' => 'tp-m3-baranda-candido', 'approved' => true],
                ['first_name' => 'Demetria', 'last_name' => 'Barrion', 'email' => 'demetria.bar@mail.demo', 'phone' => '09181000004', 'address' => '4 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2014-05-18', 'slug' => 'tp-m4-barrion-demetria', 'approved' => true],
                ['first_name' => 'Emiliano', 'last_name' => 'Batac', 'email' => 'emiliano.bat@mail.demo', 'phone' => '09181000005', 'address' => '5 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2014-06-25', 'slug' => 'tp-m5-batac-emiliano', 'approved' => true],
                ['first_name' => 'Fidela', 'last_name' => 'Batungbakal', 'email' => 'fidela.bat@mail.demo', 'phone' => '09181000006', 'address' => '6 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2014-07-10', 'slug' => 'tp-m6-batungbakal-fidela', 'approved' => true],
                ['first_name' => 'Gavino', 'last_name' => 'Bayani', 'email' => 'gavino.bay@mail.demo', 'phone' => '09181000007', 'address' => '7 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Pointman', 'status' => 'Inactive', 'join_date' => '2014-08-18', 'slug' => 'tp-m7-bayani-gavino', 'approved' => true],
                ['first_name' => 'Honoria', 'last_name' => 'Belda', 'email' => 'honoria.bel@mail.demo', 'phone' => '09181000008', 'address' => '8 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2014-09-25', 'slug' => 'tp-m8-belda-honoria', 'approved' => true],
                ['first_name' => 'Ignacio', 'last_name' => 'Benedicto', 'email' => 'ignacio.ben@mail.demo', 'phone' => '09181000009', 'address' => '9 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2014-10-30', 'slug' => 'tp-m9-benedicto-ignacio', 'approved' => true],
                ['first_name' => 'Jacoba', 'last_name' => 'Bernal', 'email' => 'jacoba.bern@mail.demo', 'phone' => '09181000010', 'address' => '10 Trespuntos Rd, Tandang Sora', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2014-11-15', 'slug' => 'tp-m10-bernal-jacoba', 'approved' => false],
            ],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fairviewMemberData(): array
    {
        return [
            'Northside Locos' => [
                ['first_name' => 'Paolo', 'last_name' => 'Cortez', 'email' => 'paolo.cortez@fairview.demo', 'phone' => '09191000001', 'address' => '1 Northside St, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2021-01-10', 'slug' => 'nl-m1-cortez-paolo', 'approved' => true],
                ['first_name' => 'Jasmine', 'last_name' => 'Alcantara', 'email' => 'jasmine.alcantara@fairview.demo', 'phone' => '09191000002', 'address' => '2 Northside St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-02-15', 'slug' => 'nl-m2-alcantara-jasmine', 'approved' => true],
                ['first_name' => 'Kenneth', 'last_name' => 'Soriano', 'email' => 'kenneth.soriano@fairview.demo', 'phone' => '09191000003', 'address' => '3 Northside St, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2021-03-20', 'slug' => 'nl-m3-soriano-kenneth', 'approved' => true],
                ['first_name' => 'Rachel', 'last_name' => 'Mercado', 'email' => 'rachel.mercado@fairview.demo', 'phone' => '09191000004', 'address' => '4 Northside St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-04-12', 'slug' => 'nl-m4-mercado-rachel', 'approved' => true],
                ['first_name' => 'Dexter', 'last_name' => 'Panganiban', 'email' => 'dexter.p@fairview.demo', 'phone' => '09191000005', 'address' => '5 Northside St, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2021-05-18', 'slug' => 'nl-m5-panganiban-dexter', 'approved' => true],
                ['first_name' => 'Michelle', 'last_name' => 'Manalo', 'email' => 'michelle.manalo@fairview.demo', 'phone' => '09191000006', 'address' => '6 Northside St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-06-25', 'slug' => 'nl-m6-manalo-michelle', 'approved' => true],
                ['first_name' => 'Jeffrey', 'last_name' => 'Beltran', 'email' => 'jeffrey.beltran@fairview.demo', 'phone' => '09191000007', 'address' => '7 Northside St, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2021-07-30', 'slug' => 'nl-m7-beltran-jeffrey', 'approved' => true],
                ['first_name' => 'Kristine', 'last_name' => 'Valdez', 'email' => 'kristine.valdez@fairview.demo', 'phone' => '09191000008', 'address' => '8 Northside St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2021-08-14', 'slug' => 'nl-m8-valdez-kristine', 'approved' => true],
                ['first_name' => 'Marvin', 'last_name' => 'Santiago', 'email' => 'marvin.santiago@fairview.demo', 'phone' => '09191000009', 'address' => '9 Northside St, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2021-09-09', 'slug' => 'nl-m9-santiago-marvin', 'approved' => true],
                ['first_name' => 'Sheila', 'last_name' => 'Ocampo', 'email' => 'sheila.ocampo@fairview.demo', 'phone' => '09191000010', 'address' => '10 Northside St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2022-01-11', 'slug' => 'nl-m10-ocampo-sheila', 'approved' => false],
            ],

            'Vandalz' => [
                ['first_name' => 'Rafael', 'last_name' => 'Esguerra', 'email' => 'rafael.esguerra@fairview.demo', 'phone' => '09192000001', 'address' => '1 Vandalz Alley, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2020-03-14', 'slug' => 'vd-m1-esguerra-rafael', 'approved' => true],
                ['first_name' => 'Diane', 'last_name' => 'Tolentino', 'email' => 'diane.tolentino@fairview.demo', 'phone' => '09192000002', 'address' => '2 Vandalz Alley, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-04-20', 'slug' => 'vd-m2-tolentino-diane', 'approved' => true],
                ['first_name' => 'Christian', 'last_name' => 'Macaraeg', 'email' => 'christian.m@fairview.demo', 'phone' => '09192000003', 'address' => '3 Vandalz Alley, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2020-05-25', 'slug' => 'vd-m3-macaraeg-christian', 'approved' => true],
                ['first_name' => 'Alyssa', 'last_name' => 'Domingo', 'email' => 'alyssa.domingo@fairview.demo', 'phone' => '09192000004', 'address' => '4 Vandalz Alley, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-06-18', 'slug' => 'vd-m4-domingo-alyssa', 'approved' => true],
                ['first_name' => 'Gerard', 'last_name' => 'Pascual', 'email' => 'gerard.pascual@fairview.demo', 'phone' => '09192000005', 'address' => '5 Vandalz Alley, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2020-07-22', 'slug' => 'vd-m5-pascual-gerard', 'approved' => true],
                ['first_name' => 'Bea', 'last_name' => 'Claravall', 'email' => 'bea.claravall@fairview.demo', 'phone' => '09192000006', 'address' => '6 Vandalz Alley, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-08-30', 'slug' => 'vd-m6-claravall-bea', 'approved' => true],
                ['first_name' => 'Joel', 'last_name' => 'Crisostomo', 'email' => 'joel.crisostomo@fairview.demo', 'phone' => '09192000007', 'address' => '7 Vandalz Alley, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2020-09-12', 'slug' => 'vd-m7-crisostomo-joel', 'approved' => true],
                ['first_name' => 'Patricia', 'last_name' => 'Samonte', 'email' => 'patricia.samonte@fairview.demo', 'phone' => '09192000008', 'address' => '8 Vandalz Alley, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2020-10-05', 'slug' => 'vd-m8-samonte-patricia', 'approved' => true],
                ['first_name' => 'Dennis', 'last_name' => 'Magno', 'email' => 'dennis.magno@fairview.demo', 'phone' => '09192000009', 'address' => '9 Vandalz Alley, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2020-11-19', 'slug' => 'vd-m9-magno-dennis', 'approved' => true],
                ['first_name' => 'Angela', 'last_name' => 'Cuenca', 'email' => 'angela.cuenca@fairview.demo', 'phone' => '09192000010', 'address' => '10 Vandalz Alley, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2021-02-08', 'slug' => 'vd-m10-cuenca-angela', 'approved' => false],
            ],

            'Barrio Trece' => [
                ['first_name' => 'Dante', 'last_name' => 'Madrigal', 'email' => 'dante.madrigal@fairview.demo', 'phone' => '09193000001', 'address' => '1 Barrio Trece St, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2019-05-10', 'slug' => 'bt-m1-madrigal-dante', 'approved' => true],
                ['first_name' => 'Katrina', 'last_name' => 'De Leon', 'email' => 'katrina.deleon@fairview.demo', 'phone' => '09193000002', 'address' => '2 Barrio Trece St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-06-15', 'slug' => 'bt-m2-deleon-katrina', 'approved' => true],
                ['first_name' => 'Victorino', 'last_name' => 'Salcedo', 'email' => 'victorino.s@fairview.demo', 'phone' => '09193000003', 'address' => '3 Barrio Trece St, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2019-07-20', 'slug' => 'bt-m3-salcedo-victorino', 'approved' => true],
                ['first_name' => 'Clarissa', 'last_name' => 'Guevarra', 'email' => 'clarissa.g@fairview.demo', 'phone' => '09193000004', 'address' => '4 Barrio Trece St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-08-11', 'slug' => 'bt-m4-guevarra-clarissa', 'approved' => true],
                ['first_name' => 'Rommel', 'last_name' => 'Trinidad', 'email' => 'rommel.trinidad@fairview.demo', 'phone' => '09193000005', 'address' => '5 Barrio Trece St, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2019-09-25', 'slug' => 'bt-m5-trinidad-rommel', 'approved' => true],
                ['first_name' => 'Rowena', 'last_name' => 'Galvez', 'email' => 'rowena.galvez@fairview.demo', 'phone' => '09193000006', 'address' => '6 Barrio Trece St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-10-18', 'slug' => 'bt-m6-galvez-rowena', 'approved' => true],
                ['first_name' => 'Arthur', 'last_name' => 'Cajucom', 'email' => 'arthur.cajucom@fairview.demo', 'phone' => '09193000007', 'address' => '7 Barrio Trece St, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2019-11-22', 'slug' => 'bt-m7-cajucom-arthur', 'approved' => true],
                ['first_name' => 'Melissa', 'last_name' => 'Quizon', 'email' => 'melissa.quizon@fairview.demo', 'phone' => '09193000008', 'address' => '8 Barrio Trece St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2019-12-05', 'slug' => 'bt-m8-quizon-melissa', 'approved' => true],
                ['first_name' => 'Nestor', 'last_name' => 'Evangelista', 'email' => 'nestor.e@fairview.demo', 'phone' => '09193000009', 'address' => '9 Barrio Trece St, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2020-01-14', 'slug' => 'bt-m9-evangelista-nestor', 'approved' => true],
                ['first_name' => 'Teresa', 'last_name' => 'Roxas', 'email' => 'teresa.roxas@fairview.demo', 'phone' => '09193000010', 'address' => '10 Barrio Trece St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2020-03-20', 'slug' => 'bt-m10-roxas-teresa', 'approved' => false],
            ],

            'Maligaya Locos' => [
                ['first_name' => 'Oscar', 'last_name' => 'Buenaflor', 'email' => 'oscar.buenaflor@fairview.demo', 'phone' => '09194000001', 'address' => '1 Maligaya Ave, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2018-08-12', 'slug' => 'ml-m1-buenaflor-oscar', 'approved' => true],
                ['first_name' => 'Jessica', 'last_name' => 'Dimaculangan', 'email' => 'jessica.d@fairview.demo', 'phone' => '09194000002', 'address' => '2 Maligaya Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-09-18', 'slug' => 'ml-m2-dimaculangan-jessica', 'approved' => true],
                ['first_name' => 'Reynaldo', 'last_name' => 'Agoncillo', 'email' => 'reynaldo.a@fairview.demo', 'phone' => '09194000003', 'address' => '3 Maligaya Ave, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2018-10-24', 'slug' => 'ml-m3-agoncillo-reynaldo', 'approved' => true],
                ['first_name' => 'Camille', 'last_name' => 'Fajardo', 'email' => 'camille.fajardo@fairview.demo', 'phone' => '09194000004', 'address' => '4 Maligaya Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-11-15', 'slug' => 'ml-m4-fajardo-camille', 'approved' => true],
                ['first_name' => 'Norman', 'last_name' => 'Laurel', 'email' => 'norman.laurel@fairview.demo', 'phone' => '09194000005', 'address' => '5 Maligaya Ave, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2018-12-08', 'slug' => 'ml-m5-laurel-norman', 'approved' => true],
                ['first_name' => 'Lilibeth', 'last_name' => 'Catacutan', 'email' => 'lilibeth.c@fairview.demo', 'phone' => '09194000006', 'address' => '6 Maligaya Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-01-20', 'slug' => 'ml-m6-catacutan-lilibeth', 'approved' => true],
                ['first_name' => 'Marlon', 'last_name' => 'Legaspi', 'email' => 'marlon.legaspi@fairview.demo', 'phone' => '09194000007', 'address' => '7 Maligaya Ave, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2019-02-14', 'slug' => 'ml-m7-legaspi-marlon', 'approved' => true],
                ['first_name' => 'Karen', 'last_name' => 'Malabanan', 'email' => 'karen.malabanan@fairview.demo', 'phone' => '09194000008', 'address' => '8 Maligaya Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2019-03-22', 'slug' => 'ml-m8-malabanan-karen', 'approved' => true],
                ['first_name' => 'Eric', 'last_name' => 'Pineda', 'email' => 'eric.pineda@fairview.demo', 'phone' => '09194000009', 'address' => '9 Maligaya Ave, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2019-04-17', 'slug' => 'ml-m9-pineda-eric', 'approved' => true],
                ['first_name' => 'Abigail', 'last_name' => 'Sison', 'email' => 'abigail.sison@fairview.demo', 'phone' => '09194000010', 'address' => '10 Maligaya Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2019-06-10', 'slug' => 'ml-m10-sison-abigail', 'approved' => false],
            ],

            'South Fairview Krew' => [
                ['first_name' => 'Arnel', 'last_name' => 'Macapagal', 'email' => 'arnel.macapagal@fairview.demo', 'phone' => '09195000001', 'address' => '1 South Fairview St, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2021-04-05', 'slug' => 'sf-m1-macapagal-arnel', 'approved' => true],
                ['first_name' => 'Monica', 'last_name' => 'Guinto', 'email' => 'monica.guinto@fairview.demo', 'phone' => '09195000002', 'address' => '2 South Fairview St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-05-12', 'slug' => 'sf-m2-guinto-monica', 'approved' => true],
                ['first_name' => 'Jayvee', 'last_name' => 'Ronquillo', 'email' => 'jayvee.ronquillo@fairview.demo', 'phone' => '09195000003', 'address' => '3 South Fairview St, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2021-06-20', 'slug' => 'sf-m3-ronquillo-jayvee', 'approved' => true],
                ['first_name' => 'Charlene', 'last_name' => 'Paras', 'email' => 'charlene.paras@fairview.demo', 'phone' => '09195000004', 'address' => '4 South Fairview St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-07-15', 'slug' => 'sf-m4-paras-charlene', 'approved' => true],
                ['first_name' => 'Allan', 'last_name' => 'Policarpio', 'email' => 'allan.policarpio@fairview.demo', 'phone' => '09195000005', 'address' => '5 South Fairview St, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2021-08-25', 'slug' => 'sf-m5-policarpio-allan', 'approved' => true],
                ['first_name' => 'Vanessa', 'last_name' => 'Gatus', 'email' => 'vanessa.gatus@fairview.demo', 'phone' => '09195000006', 'address' => '6 South Fairview St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2021-09-18', 'slug' => 'sf-m6-gatus-vanessa', 'approved' => true],
                ['first_name' => 'Noel', 'last_name' => 'Balagtas', 'email' => 'noel.balagtas@fairview.demo', 'phone' => '09195000007', 'address' => '7 South Fairview St, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2021-10-30', 'slug' => 'sf-m7-balagtas-noel', 'approved' => true],
                ['first_name' => 'Rochelle', 'last_name' => 'Mallari', 'email' => 'rochelle.mallari@fairview.demo', 'phone' => '09195000008', 'address' => '8 South Fairview St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2021-11-22', 'slug' => 'sf-m8-mallari-rochelle', 'approved' => true],
                ['first_name' => 'Christopher', 'last_name' => 'David', 'email' => 'christopher.d@fairview.demo', 'phone' => '09195000009', 'address' => '9 South Fairview St, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2022-01-14', 'slug' => 'sf-m9-david-christopher', 'approved' => true],
                ['first_name' => 'Judy Ann', 'last_name' => 'Capulong', 'email' => 'judyann.capulong@fairview.demo', 'phone' => '09195000010', 'address' => '10 South Fairview St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2022-02-28', 'slug' => 'sf-m10-capulong-judyann', 'approved' => false],
            ],

            'Ascension Boyz' => [
                ['first_name' => 'Fernando', 'last_name' => 'Solis', 'email' => 'fernando.solis@fairview.demo', 'phone' => '09196000001', 'address' => '1 Ascension Way, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2017-09-14', 'slug' => 'ab-m1-solis-fernando', 'approved' => true],
                ['first_name' => 'Maricel', 'last_name' => 'Landayan', 'email' => 'maricel.landayan@fairview.demo', 'phone' => '09196000002', 'address' => '2 Ascension Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-10-22', 'slug' => 'ab-m2-landayan-maricel', 'approved' => true],
                ['first_name' => 'Roderick', 'last_name' => 'Tiamzon', 'email' => 'roderick.tiamzon@fairview.demo', 'phone' => '09196000003', 'address' => '3 Ascension Way, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2017-11-15', 'slug' => 'ab-m3-tiamzon-roderick', 'approved' => true],
                ['first_name' => 'Jocelyn', 'last_name' => 'Simbulan', 'email' => 'jocelyn.simbulan@fairview.demo', 'phone' => '09196000004', 'address' => '4 Ascension Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-12-05', 'slug' => 'ab-m4-simbulan-jocelyn', 'approved' => true],
                ['first_name' => 'Brian', 'last_name' => 'Panlilio', 'email' => 'brian.panlilio@fairview.demo', 'phone' => '09196000005', 'address' => '5 Ascension Way, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2018-01-18', 'slug' => 'ab-m5-panlilio-brian', 'approved' => true],
                ['first_name' => 'Christine', 'last_name' => 'Datu', 'email' => 'christine.datu@fairview.demo', 'phone' => '09196000006', 'address' => '6 Ascension Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-02-25', 'slug' => 'ab-m6-datu-christine', 'approved' => true],
                ['first_name' => 'Ronald', 'last_name' => 'Yumul', 'email' => 'ronald.yumul@fairview.demo', 'phone' => '09196000007', 'address' => '7 Ascension Way, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2018-03-30', 'slug' => 'ab-m7-yumul-ronald', 'approved' => true],
                ['first_name' => 'Gladys', 'last_name' => 'Henson', 'email' => 'gladys.henson@fairview.demo', 'phone' => '09196000008', 'address' => '8 Ascension Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2018-04-12', 'slug' => 'ab-m8-henson-gladys', 'approved' => true],
                ['first_name' => 'Warren', 'last_name' => 'Galang', 'email' => 'warren.galang@fairview.demo', 'phone' => '09196000009', 'address' => '9 Ascension Way, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2018-05-20', 'slug' => 'ab-m9-galang-warren', 'approved' => true],
                ['first_name' => 'Donna', 'last_name' => 'Manansala', 'email' => 'donna.manansala@fairview.demo', 'phone' => '09196000010', 'address' => '10 Ascension Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2018-07-08', 'slug' => 'ab-m10-manansala-donna', 'approved' => false],
            ],

            'Casa Real' => [
                ['first_name' => 'Alfonso', 'last_name' => 'Zobel', 'email' => 'alfonso.zobel@fairview.demo', 'phone' => '09197000001', 'address' => '1 Casa Real Blvd, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2016-04-10', 'slug' => 'cr-m1-zobel-alfonso', 'approved' => true],
                ['first_name' => 'Regina', 'last_name' => 'Araneta', 'email' => 'regina.araneta@fairview.demo', 'phone' => '09197000002', 'address' => '2 Casa Real Blvd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2016-05-18', 'slug' => 'cr-m2-araneta-regina', 'approved' => true],
                ['first_name' => 'Leandro', 'last_name' => 'Tuason', 'email' => 'leandro.tuason@fairview.demo', 'phone' => '09197000003', 'address' => '3 Casa Real Blvd, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2016-06-22', 'slug' => 'cr-m3-tuason-leandro', 'approved' => true],
                ['first_name' => 'Beatriz', 'last_name' => 'Montinola', 'email' => 'beatriz.montinola@fairview.demo', 'phone' => '09197000004', 'address' => '4 Casa Real Blvd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2016-07-15', 'slug' => 'cr-m4-montinola-beatriz', 'approved' => true],
                ['first_name' => 'Marco', 'last_name' => 'Yulo', 'email' => 'marco.yulo@fairview.demo', 'phone' => '09197000005', 'address' => '5 Casa Real Blvd, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2016-08-30', 'slug' => 'cr-m5-yulo-marco', 'approved' => true],
                ['first_name' => 'Corazon', 'last_name' => 'Ortigas', 'email' => 'corazon.ortigas@fairview.demo', 'phone' => '09197000006', 'address' => '6 Casa Real Blvd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2016-09-25', 'slug' => 'cr-m6-ortigas-corazon', 'approved' => true],
                ['first_name' => 'Rodrigo', 'last_name' => 'Elizalde', 'email' => 'rodrigo.elizalde@fairview.demo', 'phone' => '09197000007', 'address' => '7 Casa Real Blvd, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2016-10-12', 'slug' => 'cr-m7-elizalde-rodrigo', 'approved' => true],
                ['first_name' => 'Sofia', 'last_name' => 'Madrigal', 'email' => 'sofia.madrigal@fairview.demo', 'phone' => '09197000008', 'address' => '8 Casa Real Blvd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2016-11-05', 'slug' => 'cr-m8-madrigal-sofia', 'approved' => true],
                ['first_name' => 'Guillermo', 'last_name' => 'Soriano', 'email' => 'guillermo.s@fairview.demo', 'phone' => '09197000009', 'address' => '9 Casa Real Blvd, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2017-01-20', 'slug' => 'cr-m9-soriano-guillermo', 'approved' => true],
                ['first_name' => 'Carmen', 'last_name' => 'Roxas', 'email' => 'carmen.roxas@fairview.demo', 'phone' => '09197000010', 'address' => '10 Casa Real Blvd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2017-03-15', 'slug' => 'cr-m10-roxas-carmen', 'approved' => false],
            ],

            'Dahlia Locos' => [
                ['first_name' => 'Jaime', 'last_name' => 'Illustre', 'email' => 'jaime.illustre@fairview.demo', 'phone' => '09198000001', 'address' => '1 Dahlia St, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2020-01-12', 'slug' => 'dl-m1-illustre-jaime', 'approved' => true],
                ['first_name' => 'Jennifer', 'last_name' => 'Kalaw', 'email' => 'jennifer.kalaw@fairview.demo', 'phone' => '09198000002', 'address' => '2 Dahlia St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-02-18', 'slug' => 'dl-m2-kalaw-jennifer', 'approved' => true],
                ['first_name' => 'Anthony', 'last_name' => 'Katigbak', 'email' => 'anthony.katigbak@fairview.demo', 'phone' => '09198000003', 'address' => '3 Dahlia St, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2020-03-24', 'slug' => 'dl-m3-katigbak-anthony', 'approved' => true],
                ['first_name' => 'Clarita', 'last_name' => 'Laurel', 'email' => 'clarita.laurel@fairview.demo', 'phone' => '09198000004', 'address' => '4 Dahlia St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-04-15', 'slug' => 'dl-m4-laurel-clarita', 'approved' => true],
                ['first_name' => 'Joseph', 'last_name' => 'Leviste', 'email' => 'joseph.leviste@fairview.demo', 'phone' => '09198000005', 'address' => '5 Dahlia St, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2020-05-20', 'slug' => 'dl-m5-leviste-joseph', 'approved' => true],
                ['first_name' => 'Michelle', 'last_name' => 'Malvar', 'email' => 'michelle.malvar@fairview.demo', 'phone' => '09198000006', 'address' => '6 Dahlia St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2020-06-11', 'slug' => 'dl-m6-malvar-michelle', 'approved' => true],
                ['first_name' => 'Jonathan', 'last_name' => 'Marasigan', 'email' => 'jonathan.m@fairview.demo', 'phone' => '09198000007', 'address' => '7 Dahlia St, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2020-07-28', 'slug' => 'dl-m7-marasigan-jonathan', 'approved' => true],
                ['first_name' => 'Cheryl', 'last_name' => 'Medrano', 'email' => 'cheryl.medrano@fairview.demo', 'phone' => '09198000008', 'address' => '8 Dahlia St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2020-08-19', 'slug' => 'dl-m8-medrano-cheryl', 'approved' => true],
                ['first_name' => 'Darwin', 'last_name' => 'Mayo', 'email' => 'darwin.mayo@fairview.demo', 'phone' => '09198000009', 'address' => '9 Dahlia St, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2020-09-30', 'slug' => 'dl-m9-mayo-darwin', 'approved' => true],
                ['first_name' => 'Janice', 'last_name' => 'Noblefranca', 'email' => 'janice.n@fairview.demo', 'phone' => '09198000010', 'address' => '10 Dahlia St, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2020-11-15', 'slug' => 'dl-m10-noblefranca-janice', 'approved' => false],
            ],

            'Regalado Boys' => [
                ['first_name' => 'Edgardo', 'last_name' => 'Nepomuceno', 'email' => 'edgardo.n@fairview.demo', 'phone' => '09199000001', 'address' => '1 Regalado Ave, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2019-02-15', 'slug' => 'rb-m1-nepomuceno-edgardo', 'approved' => true],
                ['first_name' => 'Teresa', 'last_name' => 'Orosa', 'email' => 'teresa.orosa@fairview.demo', 'phone' => '09199000002', 'address' => '2 Regalado Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-03-20', 'slug' => 'rb-m2-orosa-teresa', 'approved' => true],
                ['first_name' => 'Gilbert', 'last_name' => 'Palma', 'email' => 'gilbert.palma@fairview.demo', 'phone' => '09199000003', 'address' => '3 Regalado Ave, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2019-04-25', 'slug' => 'rb-m3-palma-gilbert', 'approved' => true],
                ['first_name' => 'Cynthia', 'last_name' => 'Pardo', 'email' => 'cynthia.pardo@fairview.demo', 'phone' => '09199000004', 'address' => '4 Regalado Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-05-18', 'slug' => 'rb-m4-pardo-cynthia', 'approved' => true],
                ['first_name' => 'Edwin', 'last_name' => 'Paterno', 'email' => 'edwin.paterno@fairview.demo', 'phone' => '09199000005', 'address' => '5 Regalado Ave, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2019-06-30', 'slug' => 'rb-m5-paterno-edwin', 'approved' => true],
                ['first_name' => 'Grace', 'last_name' => 'Pelaez', 'email' => 'grace.pelaez@fairview.demo', 'phone' => '09199000006', 'address' => '6 Regalado Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2019-07-14', 'slug' => 'rb-m6-pelaez-grace', 'approved' => true],
                ['first_name' => 'Herbert', 'last_name' => 'Puyat', 'email' => 'herbert.puyat@fairview.demo', 'phone' => '09199000007', 'address' => '7 Regalado Ave, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2019-08-22', 'slug' => 'rb-m7-puyat-herbert', 'approved' => true],
                ['first_name' => 'Irene', 'last_name' => 'Quezon', 'email' => 'irene.quezon@fairview.demo', 'phone' => '09199000008', 'address' => '8 Regalado Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2019-09-10', 'slug' => 'rb-m8-quezon-irene', 'approved' => true],
                ['first_name' => 'Jayson', 'last_name' => 'Quirino', 'email' => 'jayson.quirino@fairview.demo', 'phone' => '09199000009', 'address' => '9 Regalado Ave, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2019-10-25', 'slug' => 'rb-m9-quirino-jayson', 'approved' => true],
                ['first_name' => 'Kimberly', 'last_name' => 'Recto', 'email' => 'kimberly.recto@fairview.demo', 'phone' => '09199000010', 'address' => '10 Regalado Ave, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2019-12-05', 'slug' => 'rb-m10-recto-kimberly', 'approved' => false],
            ],

            'West Fairview Cartel' => [
                ['first_name' => 'Leandro', 'last_name' => 'Roces', 'email' => 'leandro.roces@fairview.demo', 'phone' => '09201000001', 'address' => '1 West Fairview Rd, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2018-03-10', 'slug' => 'wf-m1-roces-leandro', 'approved' => true],
                ['first_name' => 'Lourdes', 'last_name' => 'Rustia', 'email' => 'lourdes.rustia@fairview.demo', 'phone' => '09201000002', 'address' => '2 West Fairview Rd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-04-16', 'slug' => 'wf-m2-rustia-lourdes', 'approved' => true],
                ['first_name' => 'Michael', 'last_name' => 'Sabater', 'email' => 'michael.sabater@fairview.demo', 'phone' => '09201000003', 'address' => '3 West Fairview Rd, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2018-05-22', 'slug' => 'wf-m3-sabater-michael', 'approved' => true],
                ['first_name' => 'Nancy', 'last_name' => 'Salonga', 'email' => 'nancy.salonga@fairview.demo', 'phone' => '09201000004', 'address' => '4 West Fairview Rd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-06-18', 'slug' => 'wf-m4-salonga-nancy', 'approved' => true],
                ['first_name' => 'Oliver', 'last_name' => 'San Diego', 'email' => 'oliver.sandiego@fairview.demo', 'phone' => '09201000005', 'address' => '5 West Fairview Rd, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2018-07-25', 'slug' => 'wf-m5-sandiego-oliver', 'approved' => true],
                ['first_name' => 'Pamela', 'last_name' => 'Sarmiento', 'email' => 'pamela.sarmiento@fairview.demo', 'phone' => '09201000006', 'address' => '6 West Fairview Rd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2018-08-30', 'slug' => 'wf-m6-sarmiento-pamela', 'approved' => true],
                ['first_name' => 'Quintin', 'last_name' => 'Sikat', 'email' => 'quintin.sikat@fairview.demo', 'phone' => '09201000007', 'address' => '7 West Fairview Rd, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2018-09-15', 'slug' => 'wf-m7-sikat-quintin', 'approved' => true],
                ['first_name' => 'Rowena', 'last_name' => 'Silverio', 'email' => 'rowena.silverio@fairview.demo', 'phone' => '09201000008', 'address' => '8 West Fairview Rd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2018-10-20', 'slug' => 'wf-m8-silverio-rowena', 'approved' => true],
                ['first_name' => 'Samuel', 'last_name' => 'Sinaguinan', 'email' => 'samuel.sinaguinan@fairview.demo', 'phone' => '09201000009', 'address' => '9 West Fairview Rd, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2018-11-12', 'slug' => 'wf-m9-sinaguinan-samuel', 'approved' => true],
                ['first_name' => 'Trina', 'last_name' => 'Singson', 'email' => 'trina.singson@fairview.demo', 'phone' => '09201000010', 'address' => '10 West Fairview Rd, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2019-01-25', 'slug' => 'wf-m10-singson-trina', 'approved' => false],
            ],

            'Ciudad Del Rey' => [
                ['first_name' => 'Teodoro', 'last_name' => 'Syquia', 'email' => 'teodoro.syquia@fairview.demo', 'phone' => '09202000001', 'address' => '1 Ciudad Del Rey Way, Fairview', 'membership_type' => 'High Table Council', 'status' => 'Active', 'join_date' => '2017-05-15', 'slug' => 'cr2-m1-syquia-teodoro', 'approved' => true],
                ['first_name' => 'Ursula', 'last_name' => 'Tambunting', 'email' => 'ursula.tambunting@fairview.demo', 'phone' => '09202000002', 'address' => '2 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-06-20', 'slug' => 'cr2-m2-tambunting-ursula', 'approved' => true],
                ['first_name' => 'Vincent', 'last_name' => 'Tanbuntiong', 'email' => 'vincent.t@fairview.demo', 'phone' => '09202000003', 'address' => '3 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Shot Caller', 'status' => 'Active', 'join_date' => '2017-07-28', 'slug' => 'cr2-m3-tanbuntiong-vincent', 'approved' => true],
                ['first_name' => 'Wilma', 'last_name' => 'Tantoco', 'email' => 'wilma.tantoco@fairview.demo', 'phone' => '09202000004', 'address' => '4 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-08-15', 'slug' => 'cr2-m4-tantoco-wilma', 'approved' => true],
                ['first_name' => 'Xavier', 'last_name' => 'Teodoro', 'email' => 'xavier.teodoro@fairview.demo', 'phone' => '09202000005', 'address' => '5 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Carnal', 'status' => 'Active', 'join_date' => '2017-09-22', 'slug' => 'cr2-m5-teodoro-xavier', 'approved' => true],
                ['first_name' => 'Yolanda', 'last_name' => 'Tinsay', 'email' => 'yolanda.tinsay@fairview.demo', 'phone' => '09202000006', 'address' => '6 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Active', 'join_date' => '2017-10-18', 'slug' => 'cr2-m6-tinsay-yolanda', 'approved' => true],
                ['first_name' => 'Zachary', 'last_name' => 'Tioseco', 'email' => 'zachary.tioseco@fairview.demo', 'phone' => '09202000007', 'address' => '7 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Alas', 'status' => 'Active', 'join_date' => '2017-11-30', 'slug' => 'cr2-m7-tioseco-zachary', 'approved' => true],
                ['first_name' => 'Abigail', 'last_name' => 'Tolentino', 'email' => 'abigail.tolentino@fairview.demo', 'phone' => '09202000008', 'address' => '8 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Inactive', 'join_date' => '2017-12-14', 'slug' => 'cr2-m8-tolentino-abigail', 'approved' => true],
                ['first_name' => 'Benjamin', 'last_name' => 'Tria', 'email' => 'benjamin.tria@fairview.demo', 'phone' => '09202000009', 'address' => '9 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Pointman', 'status' => 'Active', 'join_date' => '2018-01-20', 'slug' => 'cr2-m9-tria-benjamin', 'approved' => true],
                ['first_name' => 'Clarissa', 'last_name' => 'Tuazon', 'email' => 'clarissa.tuazon@fairview.demo', 'phone' => '09202000010', 'address' => '10 Ciudad Del Rey Way, Fairview', 'membership_type' => 'Carnala', 'status' => 'Pending', 'join_date' => '2018-02-28', 'slug' => 'cr2-m10-tuazon-clarissa', 'approved' => false],
            ],
        ];
    }
}
