<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'Elétrica',
                'slug' => 'eletrica',
                'search_terms' => 'eletricista elétrica eletrica energia',
                'services' => [
                    [
                        'name' => 'Instalação elétrica',
                        'slug' => 'instalacao-eletrica',
                        'search_terms' => 'eletricista instalação eletrica elétrica',
                    ],
                    [
                        'name' => 'Manutenção elétrica',
                        'slug' => 'manutencao-eletrica',
                        'search_terms' => 'eletricista manutenção reparo eletrico elétrico',
                    ],
                    [
                        'name' => 'Instalação de tomadas',
                        'slug' => 'instalacao-tomadas',
                        'search_terms' => 'tomada tomadas eletricista',
                    ],
                    [
                        'name' => 'Troca de disjuntores',
                        'slug' => 'troca-disjuntores',
                        'search_terms' => 'disjuntor disjuntores eletricista',
                    ],
                    [
                        'name' => 'Instalação de chuveiro',
                        'slug' => 'instalacao-chuveiro',
                        'search_terms' => 'chuveiro eletrico elétrico eletricista',
                    ],
                ],
            ],

            [
                'name' => 'Manutenção',
                'slug' => 'manutencao',
                'search_terms' => 'manutenção reparo conserto assistência',
                'services' => [
                    [
                        'name' => 'Manutenção residencial',
                        'slug' => 'manutencao-residencial',
                        'search_terms' => 'reparo casa residência residencial',
                    ],
                    [
                        'name' => 'Pequenos reparos',
                        'slug' => 'pequenos-reparos',
                        'search_terms' => 'marido aluguel reparo conserto',
                    ],
                    [
                        'name' => 'Montagem de móveis',
                        'slug' => 'montagem-moveis',
                        'search_terms' => 'montador móveis moveis montagem',
                    ],
                    [
                        'name' => 'Hidráulica',
                        'slug' => 'hidraulica',
                        'search_terms' => 'encanador hidráulica vazamento cano',
                    ],
                ],
            ],

            [
                'name' => 'Tecnologia',
                'slug' => 'tecnologia',
                'search_terms' => 'ti informática informatica computador tecnologia',
                'services' => [
                    [
                        'name' => 'Manutenção de computadores',
                        'slug' => 'manutencao-computadores',
                        'search_terms' => 'computador notebook pc informática técnico',
                    ],
                    [
                        'name' => 'Suporte de TI',
                        'slug' => 'suporte-ti',
                        'search_terms' => 'ti informática suporte técnico',
                    ],
                    [
                        'name' => 'Redes e Wi-Fi',
                        'slug' => 'redes-wifi',
                        'search_terms' => 'rede wifi wi-fi internet roteador',
                    ],
                    [
                        'name' => 'Desenvolvimento de sistemas',
                        'slug' => 'desenvolvimento-sistemas',
                        'search_terms' => 'software sistema programação programador',
                    ],
                    [
                        'name' => 'Criação de sites',
                        'slug' => 'criacao-sites',
                        'search_terms' => 'site website página web desenvolvimento',
                    ],
                ],
            ],

            [
                'name' => 'Fotografia',
                'slug' => 'fotografia',
                'search_terms' => 'fotógrafo fotografo fotos fotografia',
                'services' => [
                    [
                        'name' => 'Fotografia de eventos',
                        'slug' => 'fotografia-eventos',
                        'search_terms' => 'fotógrafo evento festa aniversário casamento',
                    ],
                    [
                        'name' => 'Ensaios fotográficos',
                        'slug' => 'ensaios-fotograficos',
                        'search_terms' => 'ensaio fotos fotógrafo book',
                    ],
                    [
                        'name' => 'Fotografia empresarial',
                        'slug' => 'fotografia-empresarial',
                        'search_terms' => 'empresa corporativo produto profissional',
                    ],
                ],
            ],

            [
                'name' => 'Contabilidade',
                'slug' => 'contabilidade',
                'search_terms' => 'contador contábil contabil imposto',
                'services' => [
                    [
                        'name' => 'Contabilidade empresarial',
                        'slug' => 'contabilidade-empresarial',
                        'search_terms' => 'contador empresa contábil',
                    ],
                    [
                        'name' => 'Abertura de empresa',
                        'slug' => 'abertura-empresa',
                        'search_terms' => 'abrir empresa mei cnpj contador',
                    ],
                    [
                        'name' => 'Imposto de renda',
                        'slug' => 'imposto-renda',
                        'search_terms' => 'irpf declaração imposto renda contador',
                    ],
                    [
                        'name' => 'Regularização de MEI',
                        'slug' => 'regularizacao-mei',
                        'search_terms' => 'mei regularizar microempreendedor',
                    ],
                ],
            ],

            [
                'name' => 'Design',
                'slug' => 'design',
                'search_terms' => 'designer arte identidade visual logo',
                'services' => [
                    [
                        'name' => 'Criação de logotipo',
                        'slug' => 'criacao-logotipo',
                        'search_terms' => 'logo logomarca identidade visual designer',
                    ],
                    [
                        'name' => 'Identidade visual',
                        'slug' => 'identidade-visual',
                        'search_terms' => 'marca branding designer',
                    ],
                    [
                        'name' => 'Artes para redes sociais',
                        'slug' => 'artes-redes-sociais',
                        'search_terms' => 'instagram facebook post social media designer',
                    ],
                ],
            ],

            [
                'name' => 'Construção',
                'slug' => 'construcao',
                'search_terms' => 'pedreiro obra construção reforma',
                'services' => [
                    [
                        'name' => 'Construção e reforma',
                        'slug' => 'construcao-reforma',
                        'search_terms' => 'pedreiro obra reforma construção',
                    ],
                    [
                        'name' => 'Pintura residencial',
                        'slug' => 'pintura-residencial',
                        'search_terms' => 'pintor pintura casa parede',
                    ],
                    [
                        'name' => 'Assentamento de pisos',
                        'slug' => 'assentamento-pisos',
                        'search_terms' => 'piso porcelanato cerâmica pedreiro',
                    ],
                    [
                        'name' => 'Alvenaria',
                        'slug' => 'alvenaria',
                        'search_terms' => 'pedreiro muro parede tijolo',
                    ],
                ],
            ],

            [
                'name' => 'Automotivo',
                'slug' => 'automotivo',
                'search_terms' => 'carro veículo veiculo automóvel automovel mecânico',
                'services' => [
                    [
                        'name' => 'Mecânica automotiva',
                        'slug' => 'mecanica-automotiva',
                        'search_terms' => 'mecânico mecanico carro oficina motor',
                    ],
                    [
                        'name' => 'Elétrica automotiva',
                        'slug' => 'eletrica-automotiva',
                        'search_terms' => 'auto elétrica eletricista carro bateria',
                    ],
                    [
                        'name' => 'Troca de óleo',
                        'slug' => 'troca-oleo',
                        'search_terms' => 'óleo oleo motor carro oficina',
                    ],
                    [
                        'name' => 'Lavagem automotiva',
                        'slug' => 'lavagem-automotiva',
                        'search_terms' => 'lava jato lavagem carro limpeza',
                    ],
                ],
            ],
            [
                'name' => 'Refrigeração e Climatização',
                'slug' => 'refrigeracao-climatizacao',
                'search_terms' => 'ar condicionado ar-condicionado refrigeração refrigeracao climatização climatizacao técnico tecnico',
                'services' => [
                    [
                        'name' => 'Instalação de ar-condicionado',
                        'slug' => 'instalacao-ar-condicionado',
                        'search_terms' => 'instalar ar condicionado split climatização técnico',
                    ],
                    [
                        'name' => 'Manutenção de ar-condicionado',
                        'slug' => 'manutencao-ar-condicionado',
                        'search_terms' => 'conserto reparo manutenção ar condicionado split técnico',
                    ],
                    [
                        'name' => 'Limpeza de ar-condicionado',
                        'slug' => 'limpeza-ar-condicionado',
                        'search_terms' => 'higienização higienizacao limpeza ar condicionado split',
                    ],
                    [
                        'name' => 'Carga de gás refrigerante',
                        'slug' => 'carga-gas-refrigerante',
                        'search_terms' => 'gás gas refrigerante ar condicionado recarga',
                    ],
                    [
                        'name' => 'Manutenção de geladeiras',
                        'slug' => 'manutencao-geladeiras',
                        'search_terms' => 'geladeira refrigerador freezer conserto refrigeração',
                    ],
                    [
                        'name' => 'Manutenção de freezers',
                        'slug' => 'manutencao-freezers',
                        'search_terms' => 'freezer refrigerador conserto refrigeração',
                    ],
                ],
            ],

            [
                'name' => 'Limpeza',
                'slug' => 'limpeza',
                'search_terms' => 'limpeza faxina diarista higienização',
                'services' => [
                    [
                        'name' => 'Limpeza residencial',
                        'slug' => 'limpeza-residencial',
                        'search_terms' => 'faxina diarista casa apartamento',
                    ],
                    [
                        'name' => 'Limpeza comercial',
                        'slug' => 'limpeza-comercial',
                        'search_terms' => 'empresa escritório loja limpeza',
                    ],
                    [
                        'name' => 'Limpeza pós-obra',
                        'slug' => 'limpeza-pos-obra',
                        'search_terms' => 'obra reforma limpeza pesada',
                    ],
                    [
                        'name' => 'Limpeza de estofados',
                        'slug' => 'limpeza-estofados',
                        'search_terms' => 'sofá sofa colchão colchao higienização',
                    ],
                ],
            ],

            [
                'name' => 'Beleza',
                'slug' => 'beleza',
                'search_terms' => 'beleza salão salao estética estetica',
                'services' => [
                    [
                        'name' => 'Cabeleireiro',
                        'slug' => 'cabeleireiro',
                        'search_terms' => 'cabelo salão corte escova',
                    ],
                    [
                        'name' => 'Manicure e pedicure',
                        'slug' => 'manicure-pedicure',
                        'search_terms' => 'unha unhas manicure pedicure',
                    ],
                    [
                        'name' => 'Maquiagem',
                        'slug' => 'maquiagem',
                        'search_terms' => 'maquiadora maquiagem evento noiva',
                    ],
                    [
                        'name' => 'Barbearia',
                        'slug' => 'barbearia',
                        'search_terms' => 'barbeiro barba cabelo masculino',
                    ],
                ],
            ],

            [
                'name' => 'Jardinagem',
                'slug' => 'jardinagem',
                'search_terms' => 'jardineiro jardim plantas poda',
                'services' => [
                    [
                        'name' => 'Manutenção de jardins',
                        'slug' => 'manutencao-jardins',
                        'search_terms' => 'jardineiro jardim manutenção',
                    ],
                    [
                        'name' => 'Poda de árvores',
                        'slug' => 'poda-arvores',
                        'search_terms' => 'poda árvore arvore galhos jardineiro',
                    ],
                    [
                        'name' => 'Roçagem',
                        'slug' => 'rocagem',
                        'search_terms' => 'roçada rocada mato terreno capina',
                    ],
                ],
            ],

            [
                'name' => 'Serralheria',
                'slug' => 'serralheria',
                'search_terms' => 'serralheiro ferro metal solda',
                'services' => [
                    [
                        'name' => 'Portões e grades',
                        'slug' => 'portoes-grades',
                        'search_terms' => 'portão portao grade ferro serralheiro',
                    ],
                    [
                        'name' => 'Solda',
                        'slug' => 'solda',
                        'search_terms' => 'soldador solda ferro metal',
                    ],
                    [
                        'name' => 'Estruturas metálicas',
                        'slug' => 'estruturas-metalicas',
                        'search_terms' => 'estrutura metálica metalica ferro cobertura',
                    ],
                ],
            ],

            [
                'name' => 'Marcenaria',
                'slug' => 'marcenaria',
                'search_terms' => 'marceneiro madeira móveis moveis planejados',
                'services' => [
                    [
                        'name' => 'Móveis planejados',
                        'slug' => 'moveis-planejados',
                        'search_terms' => 'marceneiro móveis moveis planejados armário',
                    ],
                    [
                        'name' => 'Reparo de móveis',
                        'slug' => 'reparo-moveis',
                        'search_terms' => 'conserto móvel movel marceneiro',
                    ],
                    [
                        'name' => 'Portas e estruturas de madeira',
                        'slug' => 'portas-madeira',
                        'search_terms' => 'porta madeira marceneiro',
                    ],
                ],
            ],

            [
                'name' => 'Fretes e Mudanças',
                'slug' => 'fretes-mudancas',
                'search_terms' => 'frete mudança mudanca transporte carreto',
                'services' => [
                    [
                        'name' => 'Fretes',
                        'slug' => 'fretes',
                        'search_terms' => 'frete carreto transporte caminhão',
                    ],
                    [
                        'name' => 'Mudanças residenciais',
                        'slug' => 'mudancas-residenciais',
                        'search_terms' => 'mudança mudanca casa apartamento frete',
                    ],
                    [
                        'name' => 'Transporte de móveis',
                        'slug' => 'transporte-moveis',
                        'search_terms' => 'móveis moveis transporte carreto',
                    ],
                ],
            ],

            [
                'name' => 'Assistência Técnica',
                'slug' => 'assistencia-tecnica',
                'search_terms' => 'assistência tecnica técnico conserto reparo eletrodoméstico eletrodomestico eletrônico eletronico',
                'services' => [
                    [
                        'name' => 'Manutenção de eletrodomésticos',
                        'slug' => 'manutencao-eletrodomesticos',
                        'search_terms' => 'eletrodoméstico eletrodomestico assistência técnica conserto reparo',
                    ],
                    [
                        'name' => 'Manutenção de máquinas de lavar',
                        'slug' => 'manutencao-maquinas-lavar',
                        'search_terms' => 'máquina maquina lavar lavadora conserto técnico assistência',
                    ],
                    [
                        'name' => 'Conserto de TV',
                        'slug' => 'conserto-tv',
                        'search_terms' => 'tv televisão televisao smart tv conserto assistência técnico',
                    ],
                    [
                        'name' => 'Conserto de micro-ondas',
                        'slug' => 'conserto-micro-ondas',
                        'search_terms' => 'microondas micro-ondas conserto manutenção assistência técnico',
                    ],
                    [
                        'name' => 'Manutenção de eletrônicos',
                        'slug' => 'manutencao-eletronicos',
                        'search_terms' => 'eletrônico eletronico aparelho conserto assistência técnica',
                    ],
                ],
            ],

            [
                'name' => 'Segurança Eletrônica',
                'slug' => 'seguranca-eletronica',
                'search_terms' => 'segurança seguranca eletrônica eletronica câmera camera cftv alarme cerca elétrica interfone controle acesso',
                'services' => [
                    [
                        'name' => 'Instalação de câmeras',
                        'slug' => 'instalacao-cameras',
                        'search_terms' => 'câmera camera câmeras cameras cftv vigilância monitoramento segurança',
                    ],
                    [
                        'name' => 'Instalação de alarmes',
                        'slug' => 'instalacao-alarmes',
                        'search_terms' => 'alarme alarmes segurança sensor sirene',
                    ],
                    [
                        'name' => 'Instalação de cerca elétrica',
                        'slug' => 'instalacao-cerca-eletrica',
                        'search_terms' => 'cerca elétrica eletrica segurança perímetro muro',
                    ],
                    [
                        'name' => 'Interfone e vídeo porteiro',
                        'slug' => 'interfone-video-porteiro',
                        'search_terms' => 'interfone video porteiro vídeo porteiro campainha acesso',
                    ],
                    [
                        'name' => 'Controle de acesso',
                        'slug' => 'controle-acesso',
                        'search_terms' => 'controle acesso fechadura eletrônica biometria cartão catraca',
                    ],
                ],
            ],

            [
                'name' => 'Eventos',
                'slug' => 'eventos',
                'search_terms' => 'evento festa aniversário casamento cerimônia',
                'services' => [
                    [
                        'name' => 'Decoração de eventos',
                        'slug' => 'decoracao-eventos',
                        'search_terms' => 'decoração decoracao festa casamento aniversário',
                    ],
                    [
                        'name' => 'Buffet',
                        'slug' => 'buffet',
                        'search_terms' => 'buffet comida festa evento casamento',
                    ],
                    [
                        'name' => 'Som e iluminação',
                        'slug' => 'som-iluminacao',
                        'search_terms' => 'som iluminação iluminacao festa evento',
                    ],
                    [
                        'name' => 'Cerimonial',
                        'slug' => 'cerimonial',
                        'search_terms' => 'cerimonialista casamento festa evento',
                    ],
                ],
            ],

        ];

        foreach ($catalog as $categoryIndex => $item) {
            $category = ServiceCategory::query()->updateOrCreate(
                [
                    'slug' => $item['slug'],
                ],
                [
                    'name' => $item['name'],
                    'search_terms' => $item['search_terms'],
                    'sort_order' => $categoryIndex + 1,
                    'active' => true,
                ]
            );

            foreach ($item['services'] as $serviceIndex => $service) {
                Service::query()->updateOrCreate(
                    [
                        'slug' => $service['slug'],
                    ],
                    [
                        'service_category_id' => $category->id,
                        'name' => $service['name'],
                        'search_terms' => $service['search_terms'],
                        'sort_order' => $serviceIndex + 1,
                        'active' => true,
                    ]
                );
            }
        }
    }
}
