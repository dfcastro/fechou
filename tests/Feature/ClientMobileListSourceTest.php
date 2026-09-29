<?php

namespace Tests\Feature;

use Tests\TestCase;

class ClientMobileListSourceTest extends TestCase
{
    public function test_clients_have_mobile_cards_and_desktop_table(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/pages/clients/index.blade.php'
            )
        );

        $this->assertStringContainsString(
            'LISTA MOBILE',
            $source
        );

        $this->assertStringContainsString(
            'TABELA DESKTOP',
            $source
        );

        $this->assertStringContainsString(
            'clients-mobile-list',
            $source
        );

        $this->assertStringContainsString(
            'clients-desktop-table',
            $source
        );

        $this->assertStringContainsString(
            'wire:click="edit({{ $client->id }})"',
            $source
        );

        $this->assertStringContainsString(
            'wire:click="confirmDelete({{ $client->id }})"',
            $source
        );
    }
}
