<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'store' => ['store.manage', 'block.manage', 'product.manage', 'order.manage', 'customer.view'],
            'money' => ['balance.view', 'withdrawal.request', 'withdrawal.review', 'withdrawal.pay', 'refund.process', 'ledger.view'],
            'platform' => ['user.manage', 'user.suspend', 'user.impersonate', 'plan.manage', 'setting.manage', 'audit.view'],
            'support' => ['ticket.manage', 'report.review'],
            'affiliate' => ['affiliate.join', 'affiliate.manage'],
        ];

        foreach ($permissions as $group => $slugs) {
            foreach ($slugs as $slug) {
                Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace(['.', '_'], ' ', $slug)), 'group' => $group],
                );
            }
        }

        $roles = [
            Role::CUSTOMER => [
                'name' => 'Customer',
                'description' => 'Pembeli. Bisa melihat pembelian dan mengakses produk yang dibeli.',
                'permissions' => [],
            ],
            Role::CREATOR => [
                'name' => 'Creator',
                'description' => 'Punya toko, menjual produk, dan mencairkan saldo.',
                'permissions' => [
                    'store.manage', 'block.manage', 'product.manage', 'order.manage',
                    'customer.view', 'balance.view', 'withdrawal.request', 'affiliate.manage',
                ],
            ],
            Role::AFFILIATE => [
                'name' => 'Affiliate',
                'description' => 'Mempromosikan produk creator lain dan menerima komisi.',
                'permissions' => ['affiliate.join', 'balance.view', 'withdrawal.request'],
            ],
            Role::TEAM => [
                'name' => 'Team',
                'description' => 'Anggota tim creator dengan akses operasional terbatas.',
                'permissions' => ['product.manage', 'order.manage', 'customer.view', 'block.manage'],
            ],
            Role::SUPPORT_ADMIN => [
                'name' => 'Support Admin',
                'description' => 'Menangani tiket dan moderasi, tanpa akses ke pergerakan dana.',
                'permissions' => ['ticket.manage', 'report.review', 'user.manage', 'user.suspend', 'audit.view'],
            ],
            Role::FINANCE_ADMIN => [
                'name' => 'Finance Admin',
                'description' => 'Memproses penarikan dan refund. Satu-satunya role non-super yang boleh menggerakkan dana.',
                'permissions' => ['withdrawal.review', 'withdrawal.pay', 'refund.process', 'ledger.view', 'audit.view'],
            ],
            Role::SUPER_ADMIN => [
                'name' => 'Super Admin',
                'description' => 'Akses penuh, termasuk impersonation dan pengaturan platform.',
                'permissions' => Permission::pluck('slug')->all(),
            ],
        ];

        foreach ($roles as $slug => $config) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $config['name'], 'description' => $config['description']],
            );

            $role->permissions()->sync(
                Permission::whereIn('slug', $config['permissions'])->pluck('id'),
            );
        }
    }
}
