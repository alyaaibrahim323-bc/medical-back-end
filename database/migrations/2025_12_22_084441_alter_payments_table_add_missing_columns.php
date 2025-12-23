<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $t) {

            // -------- FKs (لو مش موجودين) --------
            if (!Schema::hasColumn('payments', 'user_id')) {
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            }

            if (!Schema::hasColumn('payments', 'therapist_id')) {
                $t->foreignId('therapist_id')->nullable()->constrained('therapists')->nullOnDelete();
            }

            if (!Schema::hasColumn('payments', 'therapy_session_id')) {
                $t->foreignId('therapy_session_id')->nullable()->constrained('therapy_sessions')->nullOnDelete();
            }

            if (!Schema::hasColumn('payments', 'user_package_id')) {
                $t->foreignId('user_package_id')->nullable()->constrained('user_packages')->nullOnDelete();
            }

            // -------- Core fields --------
            if (!Schema::hasColumn('payments', 'purpose')) {
                $t->enum('purpose', ['single_session', 'package'])->after('user_package_id');
            }

            if (!Schema::hasColumn('payments', 'amount_cents')) {
                $t->integer('amount_cents')->after('purpose');
            }

            if (!Schema::hasColumn('payments', 'currency')) {
                $t->string('currency', 3)->default('EGP')->after('amount_cents');
            }

            // provider: خليها string بدل enum عشان ما تتعبوش مع تغيير providers
            if (!Schema::hasColumn('payments', 'provider')) {
                $t->string('provider')->default('kashier')->after('currency'); // أو paymob حسب اللي عندك
            }

            if (!Schema::hasColumn('payments', 'provider_order_id')) {
                $t->string('provider_order_id')->nullable()->after('provider');
            }

            if (!Schema::hasColumn('payments', 'provider_transaction_id')) {
                $t->string('provider_transaction_id')->nullable()->after('provider_order_id');
            }

            if (!Schema::hasColumn('payments', 'status')) {
                $t->enum('status', ['pending','paid','failed','refunded'])->default('pending')->after('provider_transaction_id');
            }

            if (!Schema::hasColumn('payments', 'paid_at')) {
                $t->timestamp('paid_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('payments', 'failed_at')) {
                $t->timestamp('failed_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('payments', 'refunded_at')) {
                $t->timestamp('refunded_at')->nullable()->after('failed_at');
            }

            if (!Schema::hasColumn('payments', 'payload')) {
                $t->json('payload')->nullable()->after('refunded_at');
            }

            if (!Schema::hasColumn('payments', 'reference')) {
                $t->string('reference')->unique()->after('payload');
            }

            // -------- Indexes (اختياري بس مفيد) --------
            // ملاحظة: Schema::hasIndex مش موجودة رسميًا
            // فإحنا هنحط indexes باسماء ثابتة ونحاول نضيفهم بس لو مش موجودين هنفترض أول مرة.
            // لو عندك indexes قبل كده وطلع Duplicate key name، قولي اسم الاندكس الموجود وهظبطه لك.

            if (Schema::hasColumn('payments', 'user_id') && Schema::hasColumn('payments', 'status')) {
                // ممكن يكون موجود بالفعل، لو عمل error Duplicate key name ابعتيلي الرسالة وهعمله safe
                $t->index(['user_id','status'], 'payments_user_status_idx');
            }

            if (Schema::hasColumn('payments', 'provider_order_id')) {
                $t->index(['provider_order_id'], 'payments_provider_order_idx');
            }
        });

        // ✅ لو عندك provider enum قديم (paymob فقط) وعايزة تضيفي kashier:
        // ده بيشتغل لو provider كان ENUM في الجدول القديم.
        // لو provider بالفعل string، السطر ده مش هيضر.
        try {
            DB::statement("ALTER TABLE payments MODIFY provider VARCHAR(50) NOT NULL DEFAULT 'kashier'");
        } catch (\Throwable $e) {
            // تجاهل لو مش محتاج
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {

            // drop indexes (لو موجودة)
            try { $t->dropIndex('payments_user_status_idx'); } catch (\Throwable $e) {}
            try { $t->dropIndex('payments_provider_order_idx'); } catch (\Throwable $e) {}

            // drop columns (اختياري)
            // عادة الـ down مش بنسقط الأعمدة في production، لكن هنا لو تحبي:
            // $t->dropColumn([...]);
        });
    }
};
