<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class FixTenantVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:fix-version {tenant} {version}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set a specific version as current for a tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $versionToSet = $this->argument('version');
        
        $tenant = Tenant::find($tenantId);
        
        if (!$tenant) {
            $this->error("Tenant {$tenantId} not found");
            return Command::FAILURE;
        }
        
        $this->info("Setting version {$versionToSet} as current for tenant {$tenantId}");
        
        try {
            // Initialize tenant
            tenancy()->initialize($tenant);
            
            // First unmark all current versions
            $unmarked = DB::table('system_versions')
                ->where('is_current', true)
                ->update([
                    'is_current' => false,
                    'updated_at' => now()
                ]);
                
            $this->info("Unmarked {$unmarked} previously current version(s)");
            
            // Now mark the specified version as current
            $updated = DB::table('system_versions')
                ->where('version', $versionToSet)
                ->update([
                    'is_current' => true,
                    'is_active' => true,
                    'installed_at' => now(),
                    'updated_at' => now()
                ]);
                
            if ($updated) {
                $this->info("✅ Successfully marked version {$versionToSet} as current");
                
                // Show all versions to confirm
                $versions = DB::table('system_versions')->get();
                $this->line("\nUpdated system version records:");
                foreach ($versions as $version) {
                    $status = $version->is_current ? '[CURRENT]' : ($version->is_active ? '[ACTIVE]' : '[INACTIVE]');
                    $this->line("• {$version->version} {$status}");
                }
                
                return Command::SUCCESS;
            } else {
                $this->error("❌ Version {$versionToSet} not found in system_versions table");
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");
            return Command::FAILURE;
        } finally {
            // End tenancy
            tenancy()->end();
        }
    }
}