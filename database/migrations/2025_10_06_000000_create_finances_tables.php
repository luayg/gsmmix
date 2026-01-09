<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // حساب مالي لكل مستخدم (أرقام مجمّعة لتسريع العرض)
        Schema::create('finance_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->decimal('locked_amount', 12, 2)->default(0);       // طلبات قيد التنفيذ
            $t->decimal('total_receipts', 12, 2)->default(0);       // Total credit receipts
            $t->decimal('paid_credits', 12, 2)->default(0);         // Paid credits
            $t->decimal('overdraft_limit', 12, 2)->default(0);      // Overdraft limit
            $t->timestamps();
        });

        // سجل الحركات (لـ Statement + التاريخ المالي)
        Schema::create('finance_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // kind = نوع العملية المصدرية
            $t->enum('kind', [
                'payment',        // add payment (يُعدّ Paid)
                'credit_add',     // add remote credits (قد يكون paid أو غير مدفوع)
                'credit_remove',  // remove credits
                'order_lock',     // حجز مبلغ لطلب جارٍ
                'order_release',  // تحرير/إرجاع حجز
                'overdraft_set',  // تغيير حد السحب
                'adjust'          // أي تسويات مستقبلًا
            ]);

            // direction = دخل/مصروف لأثر الرصيد
            $t->enum('direction', ['income','expense'])->index();
            $t->boolean('paid')->default(false);           // هل المبلغ محسوب ضمن "Paid credits"
            $t->decimal('amount', 12, 2);                  // قيمة العملية (+/- تُفهم من direction)
            $t->string('reference')->nullable();           // مرجع (IMEI order #... / Server order ...)
            $t->text('note')->nullable();

            // snapshot بعد العملية (لإظهار "Left amount" في Statement)
            $t->decimal('balance_after', 12, 2)->default(0);

            $t->timestamps();
            $t->index(['user_id','created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('finance_transactions');
        Schema::dropIfExists('finance_accounts');
    }
};
