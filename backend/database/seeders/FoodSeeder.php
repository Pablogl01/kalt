<?php

namespace Database\Seeders;

use App\Models\Food;
use App\Models\FoodSubstitute;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FoodSeeder extends Seeder
{
    public function run(): void
    {
        // Define foods with realistic macros per 100g
        $foodsData = [
            // --- PROTEINAS (min 25) ---
            ['nombre' => 'Pechuga de pollo', 'categoria' => 'proteina', 'calorias' => 165, 'proteina' => 31.0, 'carbos' => 0.0, 'grasa' => 3.6],
            ['nombre' => 'Pechuga de pavo', 'categoria' => 'proteina', 'calorias' => 135, 'proteina' => 30.0, 'carbos' => 0.0, 'grasa' => 1.5],
            ['nombre' => 'Ternera magra', 'categoria' => 'proteina', 'calorias' => 250, 'proteina' => 26.0, 'carbos' => 0.0, 'grasa' => 15.0],
            ['nombre' => 'Lomo de cerdo', 'categoria' => 'proteina', 'calorias' => 143, 'proteina' => 26.0, 'carbos' => 0.0, 'grasa' => 3.5],
            ['nombre' => 'Salmón fresco', 'categoria' => 'proteina', 'calorias' => 208, 'proteina' => 20.0, 'carbos' => 0.0, 'grasa' => 13.0],
            ['nombre' => 'Atún en lata al natural', 'categoria' => 'proteina', 'calorias' => 116, 'proteina' => 26.0, 'carbos' => 0.0, 'grasa' => 1.0],
            ['nombre' => 'Merluza', 'categoria' => 'proteina', 'calorias' => 78, 'proteina' => 16.5, 'carbos' => 0.0, 'grasa' => 1.2],
            ['nombre' => 'Bacalao fresco', 'categoria' => 'proteina', 'calorias' => 82, 'proteina' => 17.8, 'carbos' => 0.0, 'grasa' => 0.7],
            ['nombre' => 'Gambas/Langostinos', 'categoria' => 'proteina', 'calorias' => 85, 'proteina' => 20.1, 'carbos' => 0.0, 'grasa' => 0.5],
            ['nombre' => 'Huevos enteros', 'categoria' => 'proteina', 'calorias' => 155, 'proteina' => 13.0, 'carbos' => 1.1, 'grasa' => 11.0, 'apto_desayuno' => true],
            ['nombre' => 'Claras de huevo', 'categoria' => 'proteina', 'calorias' => 52, 'proteina' => 11.0, 'carbos' => 0.7, 'grasa' => 0.2, 'apto_desayuno' => true],
            ['nombre' => 'Tofu firme', 'categoria' => 'proteina', 'calorias' => 144, 'proteina' => 14.0, 'carbos' => 2.5, 'grasa' => 8.0],
            ['nombre' => 'Tempeh', 'categoria' => 'proteina', 'calorias' => 193, 'proteina' => 19.0, 'carbos' => 9.0, 'grasa' => 11.0],
            ['nombre' => 'Seitán', 'categoria' => 'proteina', 'calorias' => 370, 'proteina' => 75.0, 'carbos' => 14.0, 'grasa' => 1.9],
            ['nombre' => 'Requesón magro', 'categoria' => 'proteina', 'calorias' => 98, 'proteina' => 11.0, 'carbos' => 3.4, 'grasa' => 4.3, 'apto_desayuno' => true],
            ['nombre' => 'Fiambre de pavo bajo en grasa', 'categoria' => 'proteina', 'calorias' => 89, 'proteina' => 18.0, 'carbos' => 1.0, 'grasa' => 1.0, 'apto_desayuno' => true],
            ['nombre' => 'Perca', 'categoria' => 'proteina', 'calorias' => 90, 'proteina' => 19.0, 'carbos' => 0.0, 'grasa' => 0.9],
            ['nombre' => 'Lubina', 'categoria' => 'proteina', 'calorias' => 97, 'proteina' => 18.4, 'carbos' => 0.0, 'grasa' => 2.6],
            ['nombre' => 'Dorada', 'categoria' => 'proteina', 'calorias' => 77, 'proteina' => 17.0, 'carbos' => 0.0, 'grasa' => 1.0],
            ['nombre' => 'Trucha', 'categoria' => 'proteina', 'calorias' => 141, 'proteina' => 20.8, 'carbos' => 0.0, 'grasa' => 5.8],
            ['nombre' => 'Pulpo', 'categoria' => 'proteina', 'calorias' => 82, 'proteina' => 16.4, 'carbos' => 2.2, 'grasa' => 0.9],
            ['nombre' => 'Calamar', 'categoria' => 'proteina', 'calorias' => 92, 'proteina' => 15.6, 'carbos' => 3.1, 'grasa' => 1.4],
            ['nombre' => 'Carne picada de pollo', 'categoria' => 'proteina', 'calorias' => 143, 'proteina' => 20.0, 'carbos' => 0.0, 'grasa' => 7.0],
            ['nombre' => 'Solomillo de ternera', 'categoria' => 'proteina', 'calorias' => 110, 'proteina' => 21.0, 'carbos' => 0.0, 'grasa' => 2.8],
            ['nombre' => 'Proteína de suero en polvo (Whey)', 'categoria' => 'proteina', 'calorias' => 380, 'proteina' => 80.0, 'carbos' => 5.0, 'grasa' => 3.0, 'apto_desayuno' => true, 'apto_principal' => false],

            // --- CARBOHIDRATOS COMPLEJOS (min 20) ---
            ['nombre' => 'Arroz blanco', 'categoria' => 'carbos', 'calorias' => 130, 'proteina' => 2.7, 'carbos' => 28.0, 'grasa' => 0.3],
            ['nombre' => 'Arroz integral', 'categoria' => 'carbos', 'calorias' => 111, 'proteina' => 2.6, 'carbos' => 23.0, 'grasa' => 0.9],
            ['nombre' => 'Avena en copos', 'categoria' => 'carbos', 'calorias' => 389, 'proteina' => 16.9, 'carbos' => 66.0, 'grasa' => 6.9, 'apto_desayuno' => true, 'apto_principal' => false],
            ['nombre' => 'Patata cocida', 'categoria' => 'carbos', 'calorias' => 87, 'proteina' => 1.9, 'carbos' => 20.1, 'grasa' => 0.1],
            ['nombre' => 'Boniato cocido', 'categoria' => 'carbos', 'calorias' => 86, 'proteina' => 1.6, 'carbos' => 20.0, 'grasa' => 0.1],
            ['nombre' => 'Pasta integral', 'categoria' => 'carbos', 'calorias' => 124, 'proteina' => 5.3, 'carbos' => 26.5, 'grasa' => 0.5],
            ['nombre' => 'Pasta de trigo blanca', 'categoria' => 'carbos', 'calorias' => 131, 'proteina' => 5.0, 'carbos' => 27.0, 'grasa' => 0.6],
            ['nombre' => 'Pan integral de centeno', 'categoria' => 'carbos', 'calorias' => 259, 'proteina' => 9.0, 'carbos' => 48.0, 'grasa' => 3.3, 'apto_desayuno' => true],
            ['nombre' => 'Pan integral de trigo', 'categoria' => 'carbos', 'calorias' => 247, 'proteina' => 13.0, 'carbos' => 41.0, 'grasa' => 3.4, 'apto_desayuno' => true],
            ['nombre' => 'Quinoa cocida', 'categoria' => 'carbos', 'calorias' => 120, 'proteina' => 4.4, 'carbos' => 21.3, 'grasa' => 1.9],
            ['nombre' => 'Cuscús cocido', 'categoria' => 'carbos', 'calorias' => 112, 'proteina' => 3.8, 'carbos' => 23.0, 'grasa' => 0.2],
            ['nombre' => 'Garbanzos cocidos', 'categoria' => 'carbos', 'calorias' => 164, 'proteina' => 8.9, 'carbos' => 27.0, 'grasa' => 2.6],
            ['nombre' => 'Lentejas cocidas', 'categoria' => 'carbos', 'calorias' => 116, 'proteina' => 9.0, 'carbos' => 20.0, 'grasa' => 0.4],
            ['nombre' => 'Alubias blancas cocidas', 'categoria' => 'carbos', 'calorias' => 139, 'proteina' => 9.7, 'carbos' => 25.0, 'grasa' => 0.4],
            ['nombre' => 'Gofio de trigo', 'categoria' => 'carbos', 'calorias' => 360, 'proteina' => 11.0, 'carbos' => 74.0, 'grasa' => 2.5, 'apto_desayuno' => true, 'apto_principal' => false],
            ['nombre' => 'Tortitas de arroz', 'categoria' => 'carbos', 'calorias' => 387, 'proteina' => 8.0, 'carbos' => 82.0, 'grasa' => 2.8, 'apto_desayuno' => true, 'apto_principal' => false],
            ['nombre' => 'Trigo sarraceno cocido', 'categoria' => 'carbos', 'calorias' => 92, 'proteina' => 3.4, 'carbos' => 20.0, 'grasa' => 0.6],
            ['nombre' => 'Harina de avena', 'categoria' => 'carbos', 'calorias' => 379, 'proteina' => 13.0, 'carbos' => 68.0, 'grasa' => 6.5, 'apto_desayuno' => true, 'apto_principal' => false],
            ['nombre' => 'Mijo cocido', 'categoria' => 'carbos', 'calorias' => 119, 'proteina' => 3.5, 'carbos' => 23.7, 'grasa' => 1.0],
            ['nombre' => 'Yuca cocida', 'categoria' => 'carbos', 'calorias' => 160, 'proteina' => 1.4, 'carbos' => 38.0, 'grasa' => 0.3],

            // --- VERDURAS (min 20) ---
            ['nombre' => 'Brócoli al vapor', 'categoria' => 'verduras', 'calorias' => 35, 'proteina' => 2.4, 'carbos' => 7.0, 'grasa' => 0.4],
            ['nombre' => 'Espinacas frescas', 'categoria' => 'verduras', 'calorias' => 23, 'proteina' => 2.9, 'carbos' => 3.6, 'grasa' => 0.4],
            ['nombre' => 'Calabacín', 'categoria' => 'verduras', 'calorias' => 17, 'proteina' => 1.2, 'carbos' => 3.1, 'grasa' => 0.3],
            ['nombre' => 'Judías verdes cocidas', 'categoria' => 'verduras', 'calorias' => 31, 'proteina' => 1.8, 'carbos' => 7.0, 'grasa' => 0.2],
            ['nombre' => 'Tomate ensalada', 'categoria' => 'verduras', 'calorias' => 18, 'proteina' => 0.9, 'carbos' => 3.9, 'grasa' => 0.2],
            ['nombre' => 'Pepino', 'categoria' => 'verduras', 'calorias' => 15, 'proteina' => 0.7, 'carbos' => 3.6, 'grasa' => 0.1],
            ['nombre' => 'Lechuga iceberg', 'categoria' => 'verduras', 'calorias' => 14, 'proteina' => 0.9, 'carbos' => 3.0, 'grasa' => 0.1],
            ['nombre' => 'Coliflor cocida', 'categoria' => 'verduras', 'calorias' => 25, 'proteina' => 1.9, 'carbos' => 5.0, 'grasa' => 0.3],
            ['nombre' => 'Pimiento rojo', 'categoria' => 'verduras', 'calorias' => 31, 'proteina' => 1.0, 'carbos' => 6.0, 'grasa' => 0.3],
            ['nombre' => 'Pimiento verde', 'categoria' => 'verduras', 'calorias' => 20, 'proteina' => 0.9, 'carbos' => 4.6, 'grasa' => 0.2],
            ['nombre' => 'Champiñones laminados', 'categoria' => 'verduras', 'calorias' => 22, 'proteina' => 3.1, 'carbos' => 3.3, 'grasa' => 0.3],
            ['nombre' => 'Espárragos trigueros', 'categoria' => 'verduras', 'calorias' => 20, 'proteina' => 2.2, 'carbos' => 3.9, 'grasa' => 0.1],
            ['nombre' => 'Rúcula', 'categoria' => 'verduras', 'calorias' => 25, 'proteina' => 2.6, 'carbos' => 3.7, 'grasa' => 0.7],
            ['nombre' => 'Zanahoria rallada', 'categoria' => 'verduras', 'calorias' => 41, 'proteina' => 0.9, 'carbos' => 9.6, 'grasa' => 0.2],
            ['nombre' => 'Berenjena al horno', 'categoria' => 'verduras', 'calorias' => 25, 'proteina' => 1.0, 'carbos' => 6.0, 'grasa' => 0.2],
            ['nombre' => 'Cebolla picada', 'categoria' => 'verduras', 'calorias' => 40, 'proteina' => 1.1, 'carbos' => 9.3, 'grasa' => 0.1],
            ['nombre' => 'Puerro cocido', 'categoria' => 'verduras', 'calorias' => 61, 'proteina' => 1.5, 'carbos' => 14.0, 'grasa' => 0.3],
            ['nombre' => 'Alcachofa cocida', 'categoria' => 'verduras', 'calorias' => 53, 'proteina' => 2.9, 'carbos' => 10.5, 'grasa' => 0.3],
            ['nombre' => 'Repollo', 'categoria' => 'verduras', 'calorias' => 25, 'proteina' => 1.3, 'carbos' => 5.8, 'grasa' => 0.1],
            ['nombre' => 'Apio fresco', 'categoria' => 'verduras', 'calorias' => 16, 'proteina' => 0.7, 'carbos' => 3.0, 'grasa' => 0.2],

            // --- FRUTAS (min 15) ---
            ['nombre' => 'Plátano', 'categoria' => 'frutas', 'calorias' => 89, 'proteina' => 1.1, 'carbos' => 22.8, 'grasa' => 0.3],
            ['nombre' => 'Manzana golden', 'categoria' => 'frutas', 'calorias' => 52, 'proteina' => 0.3, 'carbos' => 13.8, 'grasa' => 0.2],
            ['nombre' => 'Pera de agua', 'categoria' => 'frutas', 'calorias' => 57, 'proteina' => 0.4, 'carbos' => 15.2, 'grasa' => 0.1],
            ['nombre' => 'Arándanos frescos', 'categoria' => 'frutas', 'calorias' => 57, 'proteina' => 0.7, 'carbos' => 14.5, 'grasa' => 0.3],
            ['nombre' => 'Fresas silvestres', 'categoria' => 'frutas', 'calorias' => 32, 'proteina' => 0.7, 'carbos' => 7.7, 'grasa' => 0.3],
            ['nombre' => 'Naranja dulce', 'categoria' => 'frutas', 'calorias' => 47, 'proteina' => 0.9, 'carbos' => 11.8, 'grasa' => 0.1],
            ['nombre' => 'Kiwi verde', 'categoria' => 'frutas', 'calorias' => 61, 'proteina' => 1.1, 'carbos' => 14.7, 'grasa' => 0.5],
            ['nombre' => 'Melón de cantalupo', 'categoria' => 'frutas', 'calorias' => 34, 'proteina' => 0.8, 'carbos' => 8.2, 'grasa' => 0.2],
            ['nombre' => 'Sandía refrescante', 'categoria' => 'frutas', 'calorias' => 30, 'proteina' => 0.6, 'carbos' => 7.6, 'grasa' => 0.2],
            ['nombre' => 'Piña tropical', 'categoria' => 'frutas', 'calorias' => 50, 'proteina' => 0.5, 'carbos' => 13.1, 'grasa' => 0.1],
            ['nombre' => 'Uvas blancas', 'categoria' => 'frutas', 'calorias' => 69, 'proteina' => 0.7, 'carbos' => 18.1, 'grasa' => 0.2],
            ['nombre' => 'Mango maduro', 'categoria' => 'frutas', 'calorias' => 60, 'proteina' => 0.8, 'carbos' => 15.0, 'grasa' => 0.4],
            ['nombre' => 'Ciruela roja', 'categoria' => 'frutas', 'calorias' => 46, 'proteina' => 0.7, 'carbos' => 11.4, 'grasa' => 0.3],
            ['nombre' => 'Melocotón amarillo', 'categoria' => 'frutas', 'calorias' => 39, 'proteina' => 0.9, 'carbos' => 9.5, 'grasa' => 0.3],
            ['nombre' => 'Frambuesas', 'categoria' => 'frutas', 'calorias' => 52, 'proteina' => 1.2, 'carbos' => 11.9, 'grasa' => 0.7],

            // --- GRASAS SALUDABLES (min 10) ---
            ['nombre' => 'Aceite de oliva virgen extra', 'categoria' => 'grasas', 'calorias' => 884, 'proteina' => 0.0, 'carbos' => 0.0, 'grasa' => 100.0],
            ['nombre' => 'Aguacate', 'categoria' => 'grasas', 'calorias' => 160, 'proteina' => 2.0, 'carbos' => 8.5, 'grasa' => 14.7],
            ['nombre' => 'Almendras crudas', 'categoria' => 'grasas', 'calorias' => 579, 'proteina' => 21.0, 'carbos' => 21.6, 'grasa' => 49.9],
            ['nombre' => 'Nueces peladas', 'categoria' => 'grasas', 'calorias' => 654, 'proteina' => 15.0, 'carbos' => 13.7, 'grasa' => 65.2],
            ['nombre' => 'Anacardos tostados', 'categoria' => 'grasas', 'calorias' => 553, 'proteina' => 18.2, 'carbos' => 30.2, 'grasa' => 43.8],
            ['nombre' => 'Mantequilla de cacahuete pura', 'categoria' => 'grasas', 'calorias' => 588, 'proteina' => 25.0, 'carbos' => 20.0, 'grasa' => 50.0],
            ['nombre' => 'Semillas de chía', 'categoria' => 'grasas', 'calorias' => 486, 'proteina' => 16.5, 'carbos' => 42.1, 'grasa' => 30.7],
            ['nombre' => 'Semillas de lino molidas', 'categoria' => 'grasas', 'calorias' => 534, 'proteina' => 18.3, 'carbos' => 28.9, 'grasa' => 42.2],
            ['nombre' => 'Pistachos tostados', 'categoria' => 'grasas', 'calorias' => 562, 'proteina' => 20.3, 'carbos' => 27.5, 'grasa' => 45.4],
            ['nombre' => 'Avellanas crudas', 'categoria' => 'grasas', 'calorias' => 628, 'proteina' => 15.0, 'carbos' => 16.7, 'grasa' => 60.8],

            // --- LACTEOS (min 10) ---
            ['nombre' => 'Yogur griego natural desnatado', 'categoria' => 'lacteos', 'calorias' => 57, 'proteina' => 10.0, 'carbos' => 4.0, 'grasa' => 0.0, 'apto_desayuno' => true],
            ['nombre' => 'Leche semidesnatada', 'categoria' => 'lacteos', 'calorias' => 47, 'proteina' => 3.4, 'carbos' => 4.8, 'grasa' => 1.6],
            ['nombre' => 'Queso cottage bajo en grasa', 'categoria' => 'lacteos', 'calorias' => 98, 'proteina' => 11.0, 'carbos' => 3.4, 'grasa' => 4.3, 'apto_desayuno' => true],
            ['nombre' => 'Mozzarella fresca light', 'categoria' => 'lacteos', 'calorias' => 174, 'proteina' => 19.0, 'carbos' => 1.5, 'grasa' => 10.0],
            ['nombre' => 'Queso fresco tipo Burgos light', 'categoria' => 'lacteos', 'calorias' => 100, 'proteina' => 12.0, 'carbos' => 3.0, 'grasa' => 4.0, 'apto_desayuno' => true],
            ['nombre' => 'Kéfir natural bajo en grasa', 'categoria' => 'lacteos', 'calorias' => 40, 'proteina' => 3.3, 'carbos' => 4.0, 'grasa' => 1.0],
            ['nombre' => 'Yogur natural entero', 'categoria' => 'lacteos', 'calorias' => 61, 'proteina' => 3.5, 'carbos' => 4.7, 'grasa' => 3.3],
            ['nombre' => 'Queso batido 0% grasa', 'categoria' => 'lacteos', 'calorias' => 46, 'proteina' => 8.0, 'carbos' => 3.5, 'grasa' => 0.1, 'apto_desayuno' => true],
            ['nombre' => 'Queso de cabra semicurado', 'categoria' => 'lacteos', 'calorias' => 364, 'proteina' => 22.0, 'carbos' => 1.0, 'grasa' => 30.0],
            ['nombre' => 'Leche entera', 'categoria' => 'lacteos', 'calorias' => 61, 'proteina' => 3.2, 'carbos' => 4.7, 'grasa' => 3.2],

            // --- SUPLEMENTOS (sin macros: recordatorio + compra) ---
            ['nombre' => 'Creatina monohidrato', 'categoria' => 'suplemento', 'calorias' => 0, 'proteina' => 0.0, 'carbos' => 0.0, 'grasa' => 0.0],
        ];

        // Seed foods and collect them grouped by category
        $seededFoods = [];
        foreach ($foodsData as $data) {
            $food = Food::create($data);
            $seededFoods[$data['categoria']][] = $food;
        }

        // Define substitutes for each food in the same category
        // Criterio de similitud: diferencia < 10% en macros
        foreach ($seededFoods as $category => $list) {
            $count = count($list);
            for ($i = 0; $i < $count; $i++) {
                $food = $list[$i];
                $substitutesCount = 0;

                // Scan other foods in the same category to find closest substitutes
                for ($j = 0; $j < $count; $j++) {
                    if ($i === $j) continue;
                    $other = $list[$j];

                    // Compute maximum percentage difference in protein, carbs and fat
                    $diffProt = abs($food->proteina - $other->proteina);
                    $diffCarb = abs($food->carbos - $other->carbos);
                    $diffFat = abs($food->grasa - $other->grasa);

                    $similar = true;
                    if ($food->proteina > 2 && ($diffProt / $food->proteina) > 0.5) $similar = false;
                    if ($food->carbos > 2 && ($diffCarb / $food->carbos) > 0.5) $similar = false;
                    if ($food->grasa > 2 && ($diffFat / $food->grasa) > 0.5) $similar = false;

                    // If they are reasonably similar, add as a substitute
                    if ($similar) {
                        FoodSubstitute::create([
                            'food_id' => $food->id,
                            'substitute_food_id' => $other->id,
                            'similitud_macros' => 100.0 - (max([$diffProt, $diffCarb, $diffFat]) * 2.0),
                        ]);
                        $substitutesCount++;
                    }

                    if ($substitutesCount >= 3) break;
                }

                // Fallback: if we didn't find at least 2 similar substitutes, just assign the next two in the list
                if ($substitutesCount < 2) {
                    $added = 0;
                    for ($k = 0; $k < $count; $k++) {
                        if ($i === $k) continue;
                        $other = $list[$k];

                        // Check if already added
                        $exists = FoodSubstitute::where('food_id', $food->id)
                            ->where('substitute_food_id', $other->id)
                            ->exists();

                        if (!$exists) {
                            FoodSubstitute::create([
                                'food_id' => $food->id,
                                'substitute_food_id' => $other->id,
                                'similitud_macros' => 50.0,
                            ]);
                            $added++;
                            if (($substitutesCount + $added) >= 2) break;
                        }
                    }
                }
            }
        }
    }
}
