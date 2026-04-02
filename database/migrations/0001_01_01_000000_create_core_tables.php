<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // ORGANIZATIONS
        // =============================================
        // Companies that register with Mobius.
        // One org can manage multiple brands.
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['beverage_company', 'recycling_company', 'government']);
            $table->text('description');
            $table->string('logo_path');
            $table->string('website');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // SERVICE PLANS
        // =============================================
        // Tiered service catalog. Organizations subscribe to a plan.
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2);
            $table->json('features')->comment('bin_limit, analytics_level, etc.');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // USERS
        // =============================================
        // Every person in the system. Roles determine what they can do.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable()->unique();
            $table->timestamp('phone_verified_at')->nullable()->comment('OTP verification timestamp');
            $table->string('profile_photo_path');
            $table->json('roles'); // ['admin','brand_owner','store_owner','collector','public_user']

            // Recycling engagement
            $table->unsignedInteger('points_balance')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->timestamp('last_recycled_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        // Laravel infrastructure
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // =============================================
        // SUBSCRIPTIONS
        // =============================================
        // Links an organization to a service plan with contract dates.
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('plan_id')->constrained();
            $table->enum('status', ['active', 'past_due', 'cancelled', 'expired'])->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamp('renews_at');
            $table->timestamps();
        });

        // =============================================
        // PAYMENTS
        // =============================================
        // Actual payment records for subscriptions.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('subscription_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('MYR');
            $table->enum('method', ['card', 'bank_transfer', 'manual']);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('reference_number');
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        // =============================================
        // REGISTRATION REQUESTS
        // =============================================
        // Public form for companies wanting to join Mobius.
        // Admin can also create organizations directly (bypassing this).
        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->enum('type', ['beverage_company', 'recycling_company', 'government']);
            $table->text('description');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_notes');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // =============================================
        // BRANDS
        // =============================================
        // Beverage brands (Starbucks, Mixue, etc.) — belong to an organization.
        // Also the entity detected on cups by the AI.
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path');
            $table->text('description');
            $table->string('website');
            $table->decimal('point_multiplier', 3, 2)->default(1.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // INVITATIONS
        // =============================================
        // Brand owner invites store owners → admin approves → account created.
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained();
            $table->foreignId('invited_by')->constrained('users');
            $table->string('email');
            $table->string('name');
            $table->enum('role', ['store_owner', 'collector']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'accepted'])->default('pending');
            $table->text('admin_notes');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        // =============================================
        // OUTLETS
        // =============================================
        // Physical store locations under a brand, managed by a store owner.
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('store owner');
            $table->foreignId('brand_id')->constrained();
            $table->string('name');
            $table->text('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // BINS
        // =============================================
        // Physical smart recycling bins (ESP32-based hardware).
        // Brand comes from outlet. User authenticates via QR on bin's scanner.
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->nullable()->constrained()->comment('assigned after pairing');
            $table->string('serial_number')->unique();
            $table->enum('status', ['unpaired', 'active', 'maintenance', 'offline'])->default('unpaired');
            $table->unsignedTinyInteger('fill_level')->default(0)->comment('0-100 percentage');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamps();
        });

        // =============================================
        // BIN SESSIONS
        // =============================================
        // One person's interaction with one bin.
        // Starts anonymous, user links via QR scan at any point.
        Schema::create('bin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bin_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained()->comment('linked when user scans QR');
            $table->enum('status', ['active', 'completed', 'expired', 'terminated'])->default('active');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        // =============================================
        // DETECTION EVENTS
        // =============================================
        // One camera snapshot = one item = one row.
        // The AI's eyes: everything it sees becomes metadata here.
        Schema::create('detection_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bin_session_id')->constrained();
            $table->enum('waste_type', ['paper_cup', 'plastic_cup', 'lid', 'straw', 'napkin', 'liquid_waste']);
            $table->foreignId('detected_brand_id')->nullable()->constrained('brands')->comment('cup brand from AI, nullable for lids/straws');
            $table->unsignedTinyInteger('confidence')->comment('0-100');
            $table->string('image_path');
            $table->json('ai_output')->comment('raw model response: bounding boxes, probabilities, etc.');
            $table->timestamps();
        });

        // =============================================
        // RECYCLING TRANSACTIONS
        // =============================================
        // Points flowing in and out. Every point movement is a row.
        Schema::create('recycling_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('bin_session_id')->nullable()->constrained()->comment('null for spent/bonus/expired');
            $table->enum('type', ['earned', 'spent', 'bonus', 'expired']);
            $table->integer('points')->comment('positive for earned/bonus, negative for spent/expired');
            $table->string('description');
            $table->timestamps();
        });

        // =============================================
        // VOUCHER TEMPLATES
        // =============================================
        // Brand HQ defines voucher types. Not a fixed quantity — a template.
        Schema::create('voucher_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained();
            $table->string('name');
            $table->text('description');
            $table->enum('type', ['discount', 'free_item', 'cashback']);
            $table->decimal('value', 10, 2)->comment('e.g. 5.00 for RM5 off');
            $table->unsignedInteger('points_required');
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // =============================================
        // VOUCHER ALLOCATIONS
        // =============================================
        // Each outlet gets a quota from a voucher template.
        // Budget runs out or expires = no more claims from that outlet.
        Schema::create('voucher_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_template_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->unsignedInteger('quota');
            $table->unsignedInteger('claimed_count')->default(0);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until');
            $table->timestamps();
        });

        // =============================================
        // VOUCHER CLAIMS
        // =============================================
        // A user claims a voucher — spends points, gets the reward.
        Schema::create('voucher_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_template_id')->constrained();
            $table->foreignId('voucher_allocation_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('points_spent');
            $table->enum('status', ['claimed', 'redeemed', 'expired'])->default('claimed');
            $table->timestamp('claimed_at');
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_claims');
        Schema::dropIfExists('voucher_allocations');
        Schema::dropIfExists('voucher_templates');
        Schema::dropIfExists('recycling_transactions');
        Schema::dropIfExists('detection_events');
        Schema::dropIfExists('bin_sessions');
        Schema::dropIfExists('bins');
        Schema::dropIfExists('outlets');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('registration_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('organizations');
    }
};
