<?php

namespace App\Livewire\Project\Database\Clickhouse;

use App\Actions\Database\StartDatabaseProxy;
use App\Actions\Database\StopDatabaseProxy;
use App\Models\StandaloneClickhouse;
use Exception;
use Livewire\Component;

class General extends Component
{
    public StandaloneClickhouse $database;
    public ?string $db_url = null;
    public ?string $db_url_public = null;

    protected $listeners = ['refresh'];

    protected $rules = [
        'database.name' => 'required',
        'database.description' => 'nullable',
        'database.clickhouse_admin_user' => 'required',
        'database.clickhouse_admin_password' => 'required',
        'database.image' => 'required',
        'database.ports_mappings' => 'nullable',
        'database.is_public' => 'nullable|boolean',
        'database.public_port' => 'nullable|integer',
        'database.is_log_drain_enabled' => 'nullable|boolean',
    ];
    protected $validationAttributes = [
        'database.name' => 'Name',
        'database.description' => 'Description',
        'database.clickhouse_admin_user' => 'Postgres User',
        'database.clickhouse_admin_password' => 'Postgres Password',
        'database.image' => 'Image',
        'database.ports_mappings' => 'Port Mapping',
        'database.is_public' => 'Is Public',
        'database.public_port' => 'Public Port',
    ];
    public function mount()
    {
        $this->db_url = $this->database->get_db_url(true);
        if ($this->database->is_public) {
            $this->db_url_public = $this->database->get_db_url();
        }
    }
    public function instantSaveAdvanced() {
        try {
            if (!$this->database->destination->server->isLogDrainEnabled()) {
                $this->database->is_log_drain_enabled = false;
                $this->dispatch('error', 'Log drain is not enabled on the server. Please enable it first.');
                return;
            }
            $this->database->save();
            $this->dispatch('success', 'Database updated.');
            $this->dispatch('success', 'You need to restart the service for the changes to take effect.');
        } catch (Exception $e) {
            return handleError($e, $this);
        }
    }
    public function instantSave()
    {
        try {
            if ($this->database->is_public && !$this->database->public_port) {
                $this->dispatch('error', 'Public port is required.');
                $this->database->is_public = false;
                return;
            }
            if ($this->database->is_public) {
                if (!str($this->database->status)->startsWith('running')) {
                    $this->dispatch('error', 'Database must be started to be publicly accessible.');
                    $this->database->is_public = false;
                    return;
                }
                StartDatabaseProxy::run($this->database);
                $this->db_url_public = $this->database->get_db_url();
                $this->dispatch('success', 'Database is now publicly accessible.');
            } else {
                StopDatabaseProxy::run($this->database);
                $this->db_url_public = null;
                $this->dispatch('success', 'Database is no longer publicly accessible.');
            }
            $this->database->save();
        } catch (\Throwable $e) {
            $this->database->is_public = !$this->database->is_public;
            return handleError($e, $this);
        }
    }
    // public function save_init_script($script)
    // {
    //     $this->database->init_scripts = filter($this->database->init_scripts, fn ($s) => $s['filename'] !== $script['filename']);
    //     $this->database->init_scripts = array_merge($this->database->init_scripts, [$script]);
    //     $this->database->save();
    //     $this->dispatch('success', 'Init script saved.');
    // }

    // public function delete_init_script($script)
    // {
    //     $collection = collect($this->database->init_scripts);
    //     $found = $collection->firstWhere('filename', $script['filename']);
    //     if ($found) {
    //         $this->database->init_scripts = $collection->filter(fn ($s) => $s['filename'] !== $script['filename'])->toArray();
    //         $this->database->save();
    //         $this->refresh();
    //         $this->dispatch('success', 'Init script deleted.');
    //         return;
    //     }
    // }

    public function refresh(): void
    {
        $this->database->refresh();
    }


    public function submit()
    {
        try {
            if (str($this->database->public_port)->isEmpty()) {
                $this->database->public_port = null;
            }
            $this->validate();
            $this->database->save();
            $this->dispatch('success', 'Database updated.');
        } catch (Exception $e) {
            return handleError($e, $this);
        }
    }
}