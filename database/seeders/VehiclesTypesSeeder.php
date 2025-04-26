<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class VehiclesTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('vehicles_types')->insert([
            // ['type' => 'Camión (Mercedes Benz Sprinter, Fiat Ducato, JAC Sunray)'],
            ['type' => 'Camión - Mercedes Benz Sprinter'],
            ['type' => 'Camión - Fiat Ducato'],
            ['type' => 'Camión - JAC Sunray'],
            ['type' => 'Mini camioneta eléctrica'],
            ['type' => 'Camioneta (Nissan D22, Nissan D23)'],
            ['type' => 'Sedán|Hatchback (E10x, Aveo, Gol, Versa)'],
            ['type' => 'Tractocamión|Tráiler'],
            ['type' => 'Camión Torton'],
            ['type' => 'Otro'],
        ]);

        DB::table('centres')->insert([
            [
                'name' => 'Bimbo Ceylán',
                'responsible' => 'Pablo Herrera',
                'location' => 'Avenida Jesús Reyes Heroles 2, Bosques de Ceylan, 54160 Tlalnepantla, Méx.',
            ],
            [
                'name' => 'Barcel Tlalnepantla',
                'responsible' => 'Guadalupe Camero ',
                'location' => 'Av. Dr. Gustavo Baz 180, La Mora, 54090 Tlalnepantla, Méx.',
            ],
            [
                'name' => 'Bimbo Xochimilco',
                'responsible' => 'Elías Hernández',
                'location' => 'Santiago Tepalcatlalpan, Xochimilco, 16200 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Coacalco',
                'responsible' => 'Jonathan Razo',                    
                'location' => 'Av. Malaquías Huitrón 60, San Lorenzo Tetlixtac, 55718 San Francisco Coacalco, Méx.',
            ],
            [
                'name' => 'Bimbo Vallejo',
                'responsible' => 'Jaime Morales',
                'location' => 'Pte. 148 449, Lindavista Vallejo III Secc, Gustavo A. Madero, 07720 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Barcel Vallejo',
                'responsible' => null,
                'location' => 'Eje Central Lázaro Cárdenas 725, Nueva Industrial Vallejo, Gustavo A. Madero, 07700 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Xalostoc',
                'responsible' => 'Héctor González',
                'location' => 'Carlos B. Zetina 4101D, Industrial Xalostoc, 55348 Ecatepec de Morelos, Méx.',
            ],
            [
                'name' => 'Bimbo Los Reyes',
                'responsible' => null,
                'location' => '56470, Emiliano Zapata, 56490 Los Reyes Acaquilpan, Méx.',
            ],
            [
                'name' => 'Barcel Los Reyes',
                'responsible' => 'Juan Arnulfo',
                'location' => 'C. Tehuehuetitla 9, Emiliano Zapata, 56490 Emiliano Zapata, Méx.',
            ],
            [
                'name' => 'Bimbo Iztapalapa I',
                'responsible' => null,
                'location' => '09860, Verín 1, Cerro de la Estrella, Iztapalapa, Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo San Lorenzo',
                'responsible' => 'Alfonso Jiménez',
                'location' => 'Bilbao 202-Int. 0, San Juan Xalpa, Iztapalapa, 09850 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo La Villa',
                'responsible' => 'Miguel Martínez',
                'location' => 'Francisco Moreno 13, Delegación Col, Villa Gustavo A. Madero, 07050 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Tulltitán 2',
                'responsible' => 'Mauro Gonzalo',
                'location' => 'Hacienda Portales s/n, Sin Nombre, Fuentes del Valle, Méx.',
            ],
            [
                'name' => 'Bimbo Cuautitlán Izcalli',
                'responsible' => 'Mauro Ruiz',
                'location' => 'Cuautitlán - Teoloyucan Manzana 021, Santa Barbara, 54713 Cuautitlán Izcalli, Méx.',
            ],
            [
                'name' => 'Barcel Cuautitlán Izcalli',
                'responsible' => 'Orlando Rojo',
                'location' => 'Av. 1º de Mayo 100, Loma Bonita, 54879 Cuautitlán, Méx.',
            ],
            [
                'name' => 'Bimbo Cuautla',
                'responsible' => 'Pedro Rojas',
                'location' => 'Jantetelco - Cuautla 285, Santa Cruz, 62747 Cuautla, Mor.',
            ],
            [
                'name' => 'Bimbo Guadalajara',
                'responsible' => 'Pedro Rojas',
                'location' => 'Anillo Perif. Sur Manuel Gómez Morín 5982, Artesanos, 45598 San Pedro Tlaquepaque, Jal.',
            ],
            [
                'name' => 'Bimbo Santa Clara',
                'responsible' => 'Edgar Tinoco',
                'location' => 'Nuevo León 2, La Purisima Tulpetlac, 55405 Ecatepec de Morelos, Méx.',
            ],
            [
                'name' => 'Bimbo Temixco',
                'responsible' => 'Marco Morales',
                'location' => '62585 Campo Sotelo, Mor.',
            ],
            [
                'name' => 'Barcel Santa Clara',
                'responsible' => 'Edgar Díaz',
                'location' => 'Vía Morelos 300, Sta Maria Tulpetlac, 55400 Ecatepec de Morelos, Méx.',
            ],
            [
                'name' => 'Bimbo Azcapotzalco',
                'responsible' => 'Elizabeth Santiago',
                'location' => 'Av San Pablo Xalpa 520, San Martin Xochinahuac, Azcapotzalco, 02120 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Tepalcates',
                'responsible' => null,
                'location' => 'Miguel Hidalgo, Tepalcates, Iztapalapa, 09210 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Texcoco',
                'responsible' => 'Jesús De La O Goméz',
                'location' => 'Carretera, Lechería - Texcoco s/n, Amp. Tezoyuca, Méx.',
            ],
            [
                'name' => 'Bimbo Naucalpan',
                'responsible' => null,
                'location' => 'C. Alce Blanco Manzana 034, Alce Blanco, 53370 Naucalpan de Juárez, Méx.',
            ],
            [
                'name' => 'Bimbo Rojo Gómez',
                'responsible' => 'Valentín Andrade',
                'location' => 'Hermenegildo Galeana 53, Guadalupe del Moral, Iztapalapa, 09300 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Chabacano',
                'responsible' => 'Edgar Trejo',
                'location' => 'Av. del Taller 86, Tránsito, Cuauhtémoc, 06820 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Barcel Centeno',
                'responsible' => 'Omar Olivo',
                'location' => 'Centeno 415, Granjas México, Iztacalco, 08400 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Barcel Tláhuac',
                'responsible' => null,
                'location' => 'Av. Tlahuac 4700, Granjas Estrella, Iztapalapa, 09880 Ciudad de México, CDMX',
            ],
            [
                'name' => 'Bimbo Tizayuca',
                'responsible' => 'Jesús De La O Goméz',
                'location' => 'México 25, Nacozari, Tizayuca, Hgo.',
            ],
            [
                'name' => 'Bimbo Toluca',
                'responsible' => 'Alberto Arellano',
                'location' => 'Industria Automotriz 2, Delegación Santa María Totoltepec, 50200 Santa María Totoltepec, Méx.',
            ],
            [
                'name' => 'Bimbo Tepeji del Río',
                'responsible' => 'Oscar Bonilla',
                'location' => '42884 Tepeji del Río de Ocampo, Hgo.',
            ],
            [
                'name' => 'Bimbo Tepotzotlán',
                'responsible' => null,
                'location' => 'Carr. Querétaro - México 344, El Trebol, 54614 Tepotzotlán, Méx.',
            ],
            [
                'name' => 'Barcel Ixtapaluca',
                'responsible' => 'Humberto Pineda',
                'location' => 'Parque Industrial, C. la Espinita 300, 56535 Ixtapaluca, Méx.',
            ],
            [
                'name' => 'Bimbo Ixtapaluca',
                'responsible' => 'José Arriola',
                'location' => '56538, Av Hacienda las Ánimas 145, Geovillas Santa Barbara, Ixtapaluca, Méx.',
            ],
            [
                'name' => 'Bimbo Chalco',
                'responsible' => null,
                'location' => 'Manzana 023, Zona Industrial, 56600 Chalco de Díaz Covarrubias, Méx.',
            ],
        ]);

        DB::table('services')->insert([
            ['name' => 'Instalación de gancho para cargador'],
            ['name' => 'Instalación de interruptor de alarma de reversa con botón'],
            ['name' => 'Instalación de línea directa de faros antiniebla con botón'],
            ['name' => 'Refuerzo y reparación de estantería'],
            ['name' => 'Cambio de chapa de puerta corrediza de camioneta'],
            ['name' => 'Cambio de combinación de chapa y llave'],
            ['name' => 'Cambio de esquinero'],
            ['name' => 'Cambio de iluminación de calaveras por leds'],
            ['name' => 'Cambio de lente de cámara de reversa'],
            ['name' => 'Cambio de pantalla de cámara de reversa'],
            ['name' => 'Colocación de barricadas amarillas'],
            ['name' => 'Colocación de acrílico en puerta corrediza'],
            ['name' => 'Colocación de cinta reflejante roja en gomas de tope'],
            ['name' => 'Colocación de película en parabrisas'],
            ['name' => 'Desinstalación de eliminadores'],
            ['name' => 'Desinstalación de línea para cargador de impresora'],
            ['name' => 'Desmonte de cargador y base de camioneta eléctrica'],
            ['name' => 'Instalación de alarma de control'],
            ['name' => 'Instalación de alarma de reversa'],
            ['name' => 'Instalación de botaguas'],
            ['name' => 'Instalación de caja de aluminio para impresora'],
            ['name' => 'Instalación de cámara de reversa'],
            ['name' => 'Instalación de chapa phillips'],
            ['name' => 'Instalación de chapa trasera para camioneta'],
            ['name' => 'Instalación de chicote para tapón de gasolina'],
            ['name' => 'Instalación de conector para camionetas eléctricas'],
            ['name' => 'Instalación de controlador de velocidad'],
            ['name' => 'Instalación de dispositivo de cinturón de seguridad'],
            ['name' => 'Instalación de gomas de tope (par)'],
            ['name' => 'Instalación de leds en luces principales'],
            ['name' => 'Instalación de leds en luces principales y faros antiniebla'],
            ['name' => 'Instalación de luz interior'],
            ['name' => 'Instalación de marco de puerta'],
            ['name' => 'Instalación de mesa de trabajo'],
            ['name' => 'Instalación de pasador de chapa'],
            ['name' => 'Instalación de portaplacas'],
            ['name' => 'Instalación de poste en estantería'],
            ['name' => 'Instalación de rejilla en puerta'],
            ['name' => 'Instalación de tabla de mesa de trabajo'],
            ['name' => 'Instalación de ventilador (No incluye ventilador)'],
            ['name' => 'Lavado de caja'],
            ['name' => 'Lavado de vestiduras'],
            ['name' => 'Lavado en seco'],
            ['name' => 'Lavado exterior'],
            ['name' => 'Lavado interior'],
            ['name' => 'Lavado de motor'],
            ['name' => 'Limpieza de cabina'],
            ['name' => 'Ordenación de cableado'],
            ['name' => 'Polarizado de automóvil'],
            ['name' => 'Refuerzo de bisagra'],
            ['name' => 'Refuerzo con soldadura de defensa'],
            ['name' => 'Refuerzo de mesa de estantería'],
            ['name' => 'Refuerzo y reparación de bisagras de puerta de administración'],
            ['name' => 'Reinstalación de tope en puerta trasera'],
            ['name' => 'Reparación de chicote de chapa'],
            ['name' => 'Reparación de guía de vidrio'],
            ['name' => 'Reparación de luz en la caja'],
            ['name' => 'Retiro de calcomanías chicas de camionetas'],
            ['name' => 'Retiro de mesa de trabajo'],
            ['name' => 'Revisión de sistema de luces'],
            ['name' => 'Revisión de vida de llantas'],
            ['name' => 'Rotulación de caja de camioneta (3 lados)'],
            ['name' => 'Rotulación de carga'],
            ['name' => 'Rotulación de cofre'],
            ['name' => 'Rotulación de copete'],
            ['name' => 'Rotulación de económico'],
            ['name' => 'Rotulación de tanque de gas'],
        ]);
    }
}
