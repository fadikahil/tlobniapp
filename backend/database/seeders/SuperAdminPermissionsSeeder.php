<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class SuperAdminPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get the Super Admin role
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        
        if (!$superAdminRole) {
            $this->command->error('Super Admin role not found!');
            return;
        }
        
        // Get all permissions
        $permissions = Permission::pluck('id')->all();
        
        // Begin transaction
        DB::beginTransaction();
        
        try {
            // Clear existing permissions for Super Admin
            DB::table('role_has_permissions')
                ->where('role_id', $superAdminRole->id)
                ->delete();
            
            // Assign all permissions to Super Admin
            $rolePermissions = [];
            foreach ($permissions as $permissionId) {
                $rolePermissions[] = [
                    'permission_id' => $permissionId,
                    'role_id' => $superAdminRole->id
                ];
            }
            
            DB::table('role_has_permissions')->insert($rolePermissions);
            
            DB::commit();
            $this->command->info('All permissions assigned to Super Admin role successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Failed to assign permissions: ' . $e->getMessage());
        }
    }
} 