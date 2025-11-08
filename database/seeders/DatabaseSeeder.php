<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Video;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the RoleSeeder to create roles
        $this->call(RoleSeeder::class);
        
        // Call the UserSeeder to create admin, agency, and brand users
        $this->call(UserSeeder::class);
        
        // Create demo users as requested
        
        // 1 Admin user
        $admin = User::factory()->create([
            'name' => 'System Administrator',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => 'Admin'
        ]);

        // 2 Agency users
        $agency1 = User::factory()->create([
            'name' => 'Creative Agency Pro',
            'username' => 'agency1',
            'email' => 'agency1@example.com',
            'role' => 'Agency'
        ]);

        $agency2 = User::factory()->create([
            'name' => 'Digital Marketing Hub',
            'username' => 'agency2',
            'email' => 'agency2@example.com',
            'role' => 'Agency'
        ]);

        // 5 Brand users
        $brand1 = User::factory()->create([
            'name' => 'TechCorp Solutions',
            'username' => 'techcorp',
            'email' => 'brand1@example.com',
            'role' => 'Brand'
        ]);

        $brand2 = User::factory()->create([
            'name' => 'Fashion Forward Inc',
            'username' => 'fashionforward',
            'email' => 'brand2@example.com',
            'role' => 'Brand'
        ]);

        $brand3 = User::factory()->create([
            'name' => 'Healthy Living Co',
            'username' => 'healthyliving',
            'email' => 'brand3@example.com',
            'role' => 'Brand'
        ]);

        $brand4 = User::factory()->create([
            'name' => 'Eco Green Products',
            'username' => 'ecogreen',
            'email' => 'brand4@example.com',
            'role' => 'Brand'
        ]);

        $brand5 = User::factory()->create([
            'name' => 'Urban Lifestyle Brand',
            'username' => 'urbanlifestyle',
            'email' => 'brand5@example.com',
            'role' => 'Brand'
        ]);

        // Keep original test user for backward compatibility
        $user = User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'role' => 'Admin'
        ]);

        // Create sample campaigns for different brands
        $campaign1 = Campaign::create([
            'name' => 'Summer Sale 2024',
            'slug' => 'summer-sale-2024',
            'description' => 'Our biggest summer sale campaign with amazing discounts',
            'cta_text' => 'Shop Now',
            'cta_url' => 'https://example.com/shop',
            'is_active' => true,
            'user_id' => $brand1->id
        ]);

        $campaign2 = Campaign::create([
            'name' => 'Product Launch',
            'slug' => 'product-launch',
            'description' => 'Launching our new product line with exciting videos',
            'cta_text' => 'Learn More',
            'cta_url' => 'https://example.com/products',
            'is_active' => true,
            'user_id' => $brand2->id
        ]);

        $campaign3 = Campaign::create([
            'name' => 'Healthy Lifestyle Campaign',
            'slug' => 'healthy-lifestyle',
            'description' => 'Promoting healthy living through engaging content',
            'cta_text' => 'Start Your Journey',
            'cta_url' => 'https://example.com/health',
            'is_active' => true,
            'user_id' => $brand3->id
        ]);

        $campaign4 = Campaign::create([
            'name' => 'Eco-Friendly Initiative',
            'slug' => 'eco-friendly-initiative',
            'description' => 'Showcasing our commitment to environmental sustainability',
            'cta_text' => 'Go Green',
            'cta_url' => 'https://example.com/eco',
            'is_active' => true,
            'user_id' => $brand4->id
        ]);

        // Create sample videos for different campaigns
        Video::create([
            'title' => 'Summer Sale Promo',
            'description' => 'Check out our amazing summer deals!',
            'slug' => 'summer-sale-promo',
            'file_path' => '/videos/summer-sale.mp4',
            'thumbnail_path' => '/thumbnails/summer-sale.jpg',
            'cta_text' => 'Shop Now',
            'cta_url' => 'https://example.com/shop',
            'status' => 'active',
            'views' => 1250,
            'campaign_id' => $campaign1->id
        ]);

        Video::create([
            'title' => 'Product Demo Video',
            'description' => 'See our new product in action',
            'slug' => 'product-demo-video',
            'file_path' => '/videos/product-demo.mp4',
            'thumbnail_path' => '/thumbnails/product-demo.jpg',
            'cta_text' => 'Learn More',
            'cta_url' => 'https://example.com/products',
            'status' => 'active',
            'views' => 890,
            'campaign_id' => $campaign2->id
        ]);

        Video::create([
            'title' => 'Customer Testimonials',
            'description' => 'Hear what our customers say about us',
            'slug' => 'customer-testimonials',
            'file_path' => '/videos/testimonials.mp4',
            'thumbnail_path' => '/thumbnails/testimonials.jpg',
            'cta_text' => 'Read Reviews',
            'cta_url' => 'https://example.com/reviews',
            'status' => 'active',
            'views' => 2100,
            'campaign_id' => $campaign1->id
        ]);

        Video::create([
            'title' => 'Wellness Journey',
            'description' => 'Start your path to a healthier lifestyle',
            'slug' => 'wellness-journey',
            'file_path' => '/videos/wellness.mp4',
            'thumbnail_path' => '/thumbnails/wellness.jpg',
            'cta_text' => 'Start Your Journey',
            'cta_url' => 'https://example.com/health',
            'status' => 'active',
            'views' => 1580,
            'campaign_id' => $campaign3->id
        ]);

        Video::create([
            'title' => 'Sustainable Living',
            'description' => 'Learn how to live more sustainably',
            'slug' => 'sustainable-living',
            'file_path' => '/videos/sustainable.mp4',
            'thumbnail_path' => '/thumbnails/sustainable.jpg',
            'cta_text' => 'Go Green',
            'cta_url' => 'https://example.com/eco',
            'status' => 'active',
            'views' => 920,
            'campaign_id' => $campaign4->id
        ]);

        Video::create([
            'title' => 'Behind the Scenes',
            'description' => 'A look behind the scenes of our company',
            'slug' => 'behind-the-scenes',
            'file_path' => '/videos/behind-scenes.mp4',
            'thumbnail_path' => '/thumbnails/behind-scenes.jpg',
            'cta_text' => 'Discover More',
            'cta_url' => 'https://example.com/about',
            'status' => 'draft',
            'views' => 45,
            'campaign_id' => $campaign2->id
        ]);
    }
}
