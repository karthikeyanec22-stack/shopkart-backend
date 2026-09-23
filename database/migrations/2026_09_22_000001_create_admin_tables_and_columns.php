<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend products table if columns don't exist
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('products', 'brand')) {
                $table->string('brand')->nullable()->after('name');
            }
            if (!Schema::hasColumn('products', 'discount_price')) {
                $table->decimal('discount_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'featured')) {
                $table->boolean('featured')->default(false)->after('status');
            }
            if (!Schema::hasColumn('products', 'specifications')) {
                $table->text('specifications')->nullable();
            }
            if (!Schema::hasColumn('products', 'variants')) {
                $table->text('variants')->nullable();
            }
            if (!Schema::hasColumn('products', 'size')) {
                $table->string('size')->nullable();
            }
            if (!Schema::hasColumn('products', 'color')) {
                $table->string('color')->nullable();
            }
            if (!Schema::hasColumn('products', 'weight')) {
                $table->string('weight')->nullable();
            }
        });

        // 2. Extend users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('role');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
        });

        // 3. Extend orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('orders', 'tax_amount')) {
                $table->decimal('tax_amount', 10, 2)->default(0)->after('discount_amount');
            }
            if (!Schema::hasColumn('orders', 'coupon_id')) {
                $table->unsignedBigInteger('coupon_id')->nullable()->after('tax_amount');
            }
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->string('delivery_status')->default('pending')->after('status');
            }
        });

        // 4. Create order_status_histories
        if (!Schema::hasTable('order_status_histories')) {
            Schema::create('order_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->string('status');
                $table->text('note')->nullable();
                $table->string('changed_by')->nullable();
                $table->timestamps();
            });
        }

        // 5. Create delivery_partners
        if (!Schema::hasTable('delivery_partners')) {
            Schema::create('delivery_partners', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone');
                $table->string('email')->nullable();
                $table->string('employee_id')->nullable()->unique();
                $table->string('vehicle_number')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
            });
        }

        // 6. Create deliveries
        if (!Schema::hasTable('deliveries')) {
            Schema::create('deliveries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('delivery_partner_id')->constrained()->cascadeOnDelete();
                $table->timestamp('assigned_at')->useCurrent();
                $table->string('assigned_by')->nullable();
                $table->string('delivery_status')->default('assigned'); // assigned, picked_up, out_for_delivery, delivered, failed, cancelled
                $table->timestamp('delivered_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 7. Create coupons
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('discount_type'); // percentage, fixed
                $table->decimal('discount_value', 10, 2);
                $table->decimal('minimum_order_amount', 10, 2)->default(0);
                $table->decimal('maximum_discount', 10, 2)->nullable();
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('per_customer_limit')->default(1);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        // 8. Create coupon_usages
        if (!Schema::hasTable('coupon_usages')) {
            Schema::create('coupon_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->decimal('discount_applied', 10, 2);
                $table->timestamps();
            });
        }

        // 9. Create inventory_transactions
        if (!Schema::hasTable('inventory_transactions')) {
            Schema::create('inventory_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('type'); // addition, reduction, order_reservation, order_fulfillment, adjustment
                $table->integer('quantity');
                $table->integer('reserved_quantity')->default(0);
                $table->text('note')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('delivery_partners');
        Schema::dropIfExists('order_status_histories');
    }
};
