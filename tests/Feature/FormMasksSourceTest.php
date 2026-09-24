<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Support\BrazilianInput;
use Tests\TestCase;

class FormMasksSourceTest extends TestCase
{
    public function test_brazilian_input_normalizer(): void
    {
        $this->assertSame(
            '12345678901',
            BrazilianInput::document(
                '123.456.789-01'
            )
        );

        $this->assertSame(
            '12345678000199',
            BrazilianInput::document(
                '12.345.678/0001-99'
            )
        );

        $this->assertSame(
            '33999999999',
            BrazilianInput::phone(
                '+55 (33) 99999-9999'
            )
        );

        $this->assertSame(
            '39900000',
            BrazilianInput::cep(
                '39900-000'
            )
        );

        $this->assertSame(
            'MG',
            BrazilianInput::state(
                'mg'
            )
        );
    }

    public function test_document_validation_and_formatting_supports_alphanumeric_cnpj(): void
    {
        $this->assertSame(
            '00000000E08G12',
            BrazilianInput::document(
                '00.000.000/e08g-12'
            )
        );

        $this->assertTrue(
            BrazilianInput::isValidDocument(
                '529.982.247-25'
            )
        );

        $this->assertTrue(
            BrazilianInput::isValidDocument(
                '11.222.333/0001-81'
            )
        );

        $this->assertTrue(
            BrazilianInput::isValidDocument(
                '00.000.000/E08G-12'
            )
        );

        $this->assertFalse(
            BrazilianInput::isValidDocument(
                '10706.616/6660-0'
            )
        );

        $this->assertFalse(
            BrazilianInput::isValidDocument(
                '529.982.247-26'
            )
        );

        $this->assertSame(
            '529.982.247-25',
            BrazilianInput::formatDocument(
                '52998224725'
            )
        );

        $this->assertSame(
            '00.000.000/E08G-12',
            BrazilianInput::formatDocument(
                '00000000E08G12'
            )
        );

        $this->assertSame(
            '(38) 99158-6751',
            BrazilianInput::formatPhone(
                '38991586751'
            )
        );
    }

    public function test_business_and_client_normalize_contact_fields_on_write(): void
    {
        $business = new Business([
            'document' => '12.345.678/0001-99',
            'phone' => '(33) 3333-3333',
            'whatsapp' => '+55 (33) 99999-9999',
            'postal_code' => '39900-000',
            'state' => 'mg',
        ]);

        $this->assertSame(
            '12345678000199',
            $business->getAttributes()['document']
        );

        $this->assertSame(
            '3333333333',
            $business->getAttributes()['phone']
        );

        $this->assertSame(
            '33999999999',
            $business->getAttributes()['whatsapp']
        );

        $this->assertSame(
            '39900000',
            $business->getAttributes()['postal_code']
        );

        $this->assertSame(
            'MG',
            $business->getAttributes()['state']
        );

        $client = new Client([
            'document' => '123.456.789-01',
            'phone' => '(33) 3333-3333',
            'whatsapp' => '(33) 99999-9999',
        ]);

        $this->assertSame(
            '12345678901',
            $client->getAttributes()['document']
        );

        $this->assertSame(
            '3333333333',
            $client->getAttributes()['phone']
        );

        $this->assertSame(
            '33999999999',
            $client->getAttributes()['whatsapp']
        );
    }

    public function test_all_audited_forms_have_mask_or_input_mode_markers(): void
    {
        $checks = [
            'pages/clients/index.blade.php' => [
                'data-fechou-mask="document"',
                'data-fechou-mask="phone"',
            ],
            'pages/onboarding.blade.php' => [
                'data-fechou-mask="document"',
                'data-fechou-mask="phone"',
            ],
            'pages/quotes/create.blade.php' => [
                'newClientDocument',
                'newClientWhatsapp',
                'inputmode="decimal"',
            ],
            'pages/quotes/edit.blade.php' => [
                'newClientDocument',
                'newClientWhatsapp',
                'inputmode="decimal"',
            ],
            'pages/settings/business.blade.php' => [
                'data-fechou-mask="document"',
                'data-fechou-mask="phone"',
                'data-fechou-mask="cep"',
                'data-fechou-mask="uf"',
            ],
        ];

        foreach ($checks as $file => $needles) {
            $source = file_get_contents(
                resource_path('views/' . $file)
            );

            foreach ($needles as $needle) {
                $this->assertStringContainsString(
                    $needle,
                    $source,
                    $file . ' não contém ' . $needle
                );
            }
        }

        $layout = file_get_contents(
            resource_path(
                'views/layouts/app/sidebar.blade.php'
            )
        );

        $this->assertStringContainsString(
            "@include('partials.form-masks')",
            $layout
        );

        $partial = file_get_contents(
            resource_path(
                'views/partials/form-masks.blade.php'
            )
        );

        $this->assertStringContainsString(
            'dataset.fechouMask',
            $partial
        );

        $this->assertStringContainsString(
            '[data-fechou-mask]',
            $partial
        );
    }
}
