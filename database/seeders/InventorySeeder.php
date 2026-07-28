<?php

namespace Database\Seeders;

use App\Models\InventoryVehicle;
use Illuminate\Database\Seeder;

/**
 * Demo lot. Prices are in minor units (cents), matching the money convention.
 * Photos are left empty on purpose: the listing falls back to a drawn
 * placeholder rather than shipping stock imagery we have no licence for.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['stock_number' => 'D2401', 'year' => 2024, 'make' => 'Toyota', 'model' => 'RAV4', 'trim' => 'XLE Premium', 'body_type' => 'SUV', 'condition' => 'new', 'mileage' => 12, 'price' => 3649500, 'msrp' => 3789500, 'exterior_color' => 'Blueprint', 'interior_color' => 'Black SofTex', 'transmission' => 'Automatic', 'drivetrain' => 'AWD', 'fuel_type' => 'Gasoline', 'engine' => '2.5L 4-Cylinder', 'doors' => 4, 'seats' => 5, 'mpg_city' => 27, 'mpg_highway' => 33, 'is_featured' => true, 'features' => ['Blind Spot Monitor', 'Heated Seats', 'Apple CarPlay', 'Power Liftgate', 'Adaptive Cruise']],
            ['stock_number' => 'D2402', 'year' => 2022, 'make' => 'Honda', 'model' => 'Accord', 'trim' => 'Sport 2.0T', 'body_type' => 'Sedan', 'condition' => 'certified', 'mileage' => 28450, 'price' => 2789500, 'exterior_color' => 'Modern Steel', 'interior_color' => 'Black Leather', 'transmission' => 'Automatic', 'drivetrain' => 'FWD', 'fuel_type' => 'Gasoline', 'engine' => '2.0L Turbo', 'doors' => 4, 'seats' => 5, 'mpg_city' => 22, 'mpg_highway' => 32, 'is_featured' => true, 'features' => ['Sunroof', 'Wireless Charging', 'Lane Keep Assist', 'Remote Start']],
            ['stock_number' => 'D2403', 'year' => 2021, 'make' => 'Ford', 'model' => 'F-150', 'trim' => 'XLT SuperCrew', 'body_type' => 'Truck', 'condition' => 'used', 'mileage' => 51200, 'price' => 3895000, 'exterior_color' => 'Oxford White', 'interior_color' => 'Medium Earth Gray', 'transmission' => 'Automatic', 'drivetrain' => '4WD', 'fuel_type' => 'Gasoline', 'engine' => '3.5L EcoBoost V6', 'doors' => 4, 'seats' => 6, 'mpg_city' => 18, 'mpg_highway' => 24, 'is_featured' => true, 'features' => ['Tow Package', 'Bed Liner', 'Backup Camera', 'Running Boards']],
            ['stock_number' => 'D2404', 'year' => 2023, 'make' => 'Tesla', 'model' => 'Model 3', 'trim' => 'Long Range', 'body_type' => 'Sedan', 'condition' => 'used', 'mileage' => 18900, 'price' => 3295000, 'exterior_color' => 'Pearl White', 'interior_color' => 'Black', 'transmission' => 'Automatic', 'drivetrain' => 'AWD', 'fuel_type' => 'Electric', 'engine' => 'Dual Motor', 'doors' => 4, 'seats' => 5, 'features' => ['Autopilot', 'Glass Roof', 'Heated Steering Wheel', 'Premium Audio']],
            ['stock_number' => 'D2405', 'year' => 2020, 'make' => 'Subaru', 'model' => 'Outback', 'trim' => 'Premium', 'body_type' => 'Wagon', 'condition' => 'used', 'mileage' => 62300, 'price' => 2199500, 'exterior_color' => 'Crystal Black', 'interior_color' => 'Slate Cloth', 'transmission' => 'CVT', 'drivetrain' => 'AWD', 'fuel_type' => 'Gasoline', 'engine' => '2.5L Boxer', 'doors' => 4, 'seats' => 5, 'mpg_city' => 26, 'mpg_highway' => 33, 'features' => ['EyeSight Safety', 'Roof Rails', 'Heated Seats']],
            ['stock_number' => 'D2406', 'year' => 2024, 'make' => 'Chevrolet', 'model' => 'Equinox', 'trim' => 'RS', 'body_type' => 'SUV', 'condition' => 'new', 'mileage' => 8, 'price' => 3149500, 'msrp' => 3249500, 'exterior_color' => 'Radiant Red', 'interior_color' => 'Jet Black', 'transmission' => 'Automatic', 'drivetrain' => 'FWD', 'fuel_type' => 'Gasoline', 'engine' => '1.5L Turbo', 'doors' => 4, 'seats' => 5, 'mpg_city' => 26, 'mpg_highway' => 31, 'features' => ['Bose Audio', 'Panoramic Roof', 'Wireless CarPlay']],
            ['stock_number' => 'D2407', 'year' => 2019, 'make' => 'Jeep', 'model' => 'Wrangler', 'trim' => 'Unlimited Sahara', 'body_type' => 'SUV', 'condition' => 'used', 'mileage' => 74100, 'price' => 2995000, 'exterior_color' => 'Firecracker Red', 'interior_color' => 'Black Leather', 'transmission' => 'Automatic', 'drivetrain' => '4WD', 'fuel_type' => 'Gasoline', 'engine' => '3.6L V6', 'doors' => 4, 'seats' => 5, 'features' => ['Removable Top', 'Tow Hooks', 'All-Weather Mats']],
            ['stock_number' => 'D2408', 'year' => 2022, 'make' => 'Mazda', 'model' => 'CX-5', 'trim' => 'Grand Touring', 'body_type' => 'SUV', 'condition' => 'certified', 'mileage' => 33800, 'price' => 2849500, 'exterior_color' => 'Soul Red Crystal', 'interior_color' => 'Parchment Leather', 'transmission' => 'Automatic', 'drivetrain' => 'AWD', 'fuel_type' => 'Gasoline', 'engine' => '2.5L 4-Cylinder', 'doors' => 4, 'seats' => 5, 'mpg_city' => 24, 'mpg_highway' => 30, 'features' => ['Bose Audio', 'Ventilated Seats', 'Head-Up Display']],
            ['stock_number' => 'D2409', 'year' => 2023, 'make' => 'Hyundai', 'model' => 'Tucson', 'trim' => 'SEL Hybrid', 'body_type' => 'SUV', 'condition' => 'used', 'mileage' => 21400, 'price' => 2749500, 'exterior_color' => 'Amazon Gray', 'interior_color' => 'Black Cloth', 'transmission' => 'Automatic', 'drivetrain' => 'AWD', 'fuel_type' => 'Hybrid', 'engine' => '1.6L Turbo Hybrid', 'doors' => 4, 'seats' => 5, 'mpg_city' => 37, 'mpg_highway' => 36, 'features' => ['Digital Key', 'Heated Seats', 'Highway Drive Assist']],
            ['stock_number' => 'D2410', 'year' => 2018, 'make' => 'BMW', 'model' => '330i', 'trim' => 'xDrive', 'body_type' => 'Sedan', 'condition' => 'used', 'mileage' => 68900, 'price' => 2149500, 'exterior_color' => 'Alpine White', 'interior_color' => 'Black Dakota', 'transmission' => 'Automatic', 'drivetrain' => 'AWD', 'fuel_type' => 'Gasoline', 'engine' => '2.0L Turbo', 'doors' => 4, 'seats' => 5, 'features' => ['Sport Package', 'Navigation', 'Harman Kardon Audio']],
            ['stock_number' => 'D2411', 'year' => 2021, 'make' => 'Toyota', 'model' => 'Tacoma', 'trim' => 'TRD Off-Road', 'body_type' => 'Truck', 'condition' => 'used', 'mileage' => 44600, 'price' => 3749500, 'status' => 'pending', 'exterior_color' => 'Cement', 'interior_color' => 'Black', 'transmission' => 'Automatic', 'drivetrain' => '4WD', 'fuel_type' => 'Gasoline', 'engine' => '3.5L V6', 'doors' => 4, 'seats' => 5, 'features' => ['Crawl Control', 'Locking Differential', 'Bed Mat']],
            ['stock_number' => 'D2412', 'year' => 2020, 'make' => 'Honda', 'model' => 'Civic', 'trim' => 'EX', 'body_type' => 'Sedan', 'condition' => 'used', 'mileage' => 57200, 'price' => 1899500, 'exterior_color' => 'Cosmic Blue', 'interior_color' => 'Gray Cloth', 'transmission' => 'CVT', 'drivetrain' => 'FWD', 'fuel_type' => 'Gasoline', 'engine' => '1.5L Turbo', 'doors' => 4, 'seats' => 5, 'mpg_city' => 32, 'mpg_highway' => 42, 'features' => ['Sunroof', 'Honda Sensing', 'Remote Start']],
        ];

        foreach ($rows as $row) {
            InventoryVehicle::updateOrCreate(
                ['stock_number' => $row['stock_number']],
                $row + [
                    'listed_on' => now()->subDays(random_int(2, 60))->toDateString(),
                    'description' => "Clean {$row['year']} {$row['make']} {$row['model']}, inspected and reconditioned in our own service department. Full history available on request, and we are happy to arrange an independent inspection.",
                ]
            );
        }
    }
}
