<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('languages') || ! Schema::hasTable('language_translations')) {
            return;
        }

        $languages = [
            'en' => ['English', 'English', 'en', 'ltr', '🇬🇧'],
            'ar' => ['Arabic', 'العربية', 'ar', 'rtl', '🇯🇴'],
            'fr' => ['French', 'Français', 'fr', 'ltr', '🇫🇷'],
            'es' => ['Spanish', 'Español', 'es', 'ltr', '🇪🇸'],
            'de' => ['German', 'Deutsch', 'de', 'ltr', '🇩🇪'],
            'tr' => ['Turkish', 'Türkçe', 'tr', 'ltr', '🇹🇷'],
        ];

        $translations = [
            'en' => [
                'nav.home'=>'Home','nav.services'=>'Services','nav.products'=>'Products','nav.downloads'=>'Downloads','nav.resellers'=>'Resellers','nav.dashboard'=>'Dashboard','nav.login'=>'Log in','nav.register'=>'Register','nav.logout'=>'Logout',
                'account.workspace'=>'Workspace','account.overview'=>'Overview','account.place_order'=>'Place order','account.all_orders'=>'All orders','account.finance'=>'Finance & Account','account.add_funds'=>'Add funds','account.transactions'=>'Transactions','account.settings'=>'Settings','account.balance'=>'Balance','account.customer'=>'Customer',
                'home.kicker'=>'Global mobile solutions','home.title'=>'Powering Mobile Professionals','home.subtitle'=>'Professional tools, services and support for the mobile industry — faster, smarter, worldwide.','home.explore'=>'Explore services','home.view_products'=>'View products','home.trusted'=>'Trusted by professionals','home.instant'=>'Instant delivery','home.secure'=>'Secure & reliable','home.active_services'=>'Active services','home.countries'=>'Countries supported','home.support'=>'Expert support','home.delivery'=>'Digital delivery','home.what_we_do'=>'What we do','home.our_services'=>'Our Services','home.view_all_services'=>'View all services','home.latest_products'=>'Latest Products','home.view_all_products'=>'View all products',
                'dashboard.welcome'=>'Welcome back, :name','dashboard.summary'=>'Here’s what’s happening with your account today.','dashboard.available_balance'=>'Available balance','dashboard.locked'=>'Locked amount','dashboard.receipts'=>'Total credit receipts','dashboard.quick_order'=>'Quick order','dashboard.choose_service'=>'Choose a service type to get started.','dashboard.recent_orders'=>'Recent orders','dashboard.view_all_orders'=>'View all orders','common.language'=>'Language','common.rights'=>'All rights reserved.',
            ],
            'ar' => [
                'nav.home'=>'الرئيسية','nav.services'=>'الخدمات','nav.products'=>'المنتجات','nav.downloads'=>'التنزيلات','nav.resellers'=>'الموزعون','nav.dashboard'=>'لوحة التحكم','nav.login'=>'تسجيل الدخول','nav.register'=>'إنشاء حساب','nav.logout'=>'تسجيل الخروج',
                'account.workspace'=>'مساحة العمل','account.overview'=>'نظرة عامة','account.place_order'=>'طلب جديد','account.all_orders'=>'كل الطلبات','account.finance'=>'المالية والحساب','account.add_funds'=>'إضافة رصيد','account.transactions'=>'المعاملات','account.settings'=>'الإعدادات','account.balance'=>'الرصيد','account.customer'=>'عميل',
                'home.kicker'=>'حلول الهواتف العالمية','home.title'=>'نمنح محترفي الهواتف قوة أكبر','home.subtitle'=>'أدوات وخدمات ودعم احترافي لقطاع الهواتف — أسرع وأذكى وفي جميع أنحاء العالم.','home.explore'=>'استكشف الخدمات','home.view_products'=>'عرض المنتجات','home.trusted'=>'موثوق لدى المحترفين','home.instant'=>'تسليم فوري','home.secure'=>'آمن وموثوق','home.active_services'=>'خدمات نشطة','home.countries'=>'دولة مدعومة','home.support'=>'دعم متخصص','home.delivery'=>'تسليم رقمي','home.what_we_do'=>'ماذا نقدم','home.our_services'=>'خدماتنا','home.view_all_services'=>'عرض كل الخدمات','home.latest_products'=>'أحدث المنتجات','home.view_all_products'=>'عرض كل المنتجات',
                'dashboard.welcome'=>'مرحبًا بعودتك، :name','dashboard.summary'=>'إليك ما يحدث في حسابك اليوم.','dashboard.available_balance'=>'الرصيد المتاح','dashboard.locked'=>'المبلغ المحجوز','dashboard.receipts'=>'إجمالي الرصيد المضاف','dashboard.quick_order'=>'طلب سريع','dashboard.choose_service'=>'اختر نوع الخدمة للبدء.','dashboard.recent_orders'=>'أحدث الطلبات','dashboard.view_all_orders'=>'عرض كل الطلبات','common.language'=>'اللغة','common.rights'=>'جميع الحقوق محفوظة.',
            ],
            'fr' => [
                'nav.home'=>'Accueil','nav.services'=>'Services','nav.products'=>'Produits','nav.downloads'=>'Téléchargements','nav.resellers'=>'Revendeurs','nav.dashboard'=>'Tableau de bord','nav.login'=>'Connexion','nav.register'=>'Créer un compte','nav.logout'=>'Déconnexion',
                'account.workspace'=>'Espace de travail','account.overview'=>'Vue d’ensemble','account.place_order'=>'Nouvelle commande','account.all_orders'=>'Toutes les commandes','account.finance'=>'Finance et compte','account.add_funds'=>'Ajouter des fonds','account.transactions'=>'Transactions','account.settings'=>'Paramètres','account.balance'=>'Solde','account.customer'=>'Client',
                'home.kicker'=>'Solutions mobiles mondiales','home.title'=>'Au service des professionnels du mobile','home.subtitle'=>'Outils, services et assistance professionnels pour l’industrie mobile — plus rapides et intelligents, partout.','home.explore'=>'Explorer les services','home.view_products'=>'Voir les produits','home.trusted'=>'Approuvé par les professionnels','home.instant'=>'Livraison instantanée','home.secure'=>'Sûr et fiable','home.active_services'=>'Services actifs','home.countries'=>'Pays pris en charge','home.support'=>'Assistance experte','home.delivery'=>'Livraison numérique','home.what_we_do'=>'Notre activité','home.our_services'=>'Nos services','home.view_all_services'=>'Voir tous les services','home.latest_products'=>'Derniers produits','home.view_all_products'=>'Voir tous les produits',
                'dashboard.welcome'=>'Bon retour, :name','dashboard.summary'=>'Voici l’activité de votre compte aujourd’hui.','dashboard.available_balance'=>'Solde disponible','dashboard.locked'=>'Montant bloqué','dashboard.receipts'=>'Total des crédits reçus','dashboard.quick_order'=>'Commande rapide','dashboard.choose_service'=>'Choisissez un type de service pour commencer.','dashboard.recent_orders'=>'Commandes récentes','dashboard.view_all_orders'=>'Voir toutes les commandes','common.language'=>'Langue','common.rights'=>'Tous droits réservés.',
            ],
            'es' => [
                'nav.home'=>'Inicio','nav.services'=>'Servicios','nav.products'=>'Productos','nav.downloads'=>'Descargas','nav.resellers'=>'Distribuidores','nav.dashboard'=>'Panel','nav.login'=>'Iniciar sesión','nav.register'=>'Crear cuenta','nav.logout'=>'Cerrar sesión',
                'account.workspace'=>'Espacio de trabajo','account.overview'=>'Resumen','account.place_order'=>'Nuevo pedido','account.all_orders'=>'Todos los pedidos','account.finance'=>'Finanzas y cuenta','account.add_funds'=>'Añadir fondos','account.transactions'=>'Transacciones','account.settings'=>'Ajustes','account.balance'=>'Saldo','account.customer'=>'Cliente',
                'home.kicker'=>'Soluciones móviles globales','home.title'=>'Impulsando a los profesionales móviles','home.subtitle'=>'Herramientas, servicios y soporte profesional para la industria móvil — más rápido, inteligente y global.','home.explore'=>'Explorar servicios','home.view_products'=>'Ver productos','home.trusted'=>'Con la confianza de profesionales','home.instant'=>'Entrega instantánea','home.secure'=>'Seguro y fiable','home.active_services'=>'Servicios activos','home.countries'=>'Países compatibles','home.support'=>'Soporte experto','home.delivery'=>'Entrega digital','home.what_we_do'=>'Qué hacemos','home.our_services'=>'Nuestros servicios','home.view_all_services'=>'Ver todos los servicios','home.latest_products'=>'Últimos productos','home.view_all_products'=>'Ver todos los productos',
                'dashboard.welcome'=>'Bienvenido de nuevo, :name','dashboard.summary'=>'Esto es lo que ocurre hoy en tu cuenta.','dashboard.available_balance'=>'Saldo disponible','dashboard.locked'=>'Importe bloqueado','dashboard.receipts'=>'Créditos recibidos','dashboard.quick_order'=>'Pedido rápido','dashboard.choose_service'=>'Elige un tipo de servicio para comenzar.','dashboard.recent_orders'=>'Pedidos recientes','dashboard.view_all_orders'=>'Ver todos los pedidos','common.language'=>'Idioma','common.rights'=>'Todos los derechos reservados.',
            ],
            'de' => [
                'nav.home'=>'Startseite','nav.services'=>'Dienste','nav.products'=>'Produkte','nav.downloads'=>'Downloads','nav.resellers'=>'Händler','nav.dashboard'=>'Dashboard','nav.login'=>'Anmelden','nav.register'=>'Konto erstellen','nav.logout'=>'Abmelden',
                'account.workspace'=>'Arbeitsbereich','account.overview'=>'Übersicht','account.place_order'=>'Neue Bestellung','account.all_orders'=>'Alle Bestellungen','account.finance'=>'Finanzen & Konto','account.add_funds'=>'Guthaben hinzufügen','account.transactions'=>'Transaktionen','account.settings'=>'Einstellungen','account.balance'=>'Guthaben','account.customer'=>'Kunde',
                'home.kicker'=>'Globale Mobilfunklösungen','home.title'=>'Leistung für Mobilfunkprofis','home.subtitle'=>'Professionelle Werkzeuge, Dienste und Support für die Mobilfunkbranche — schneller, intelligenter, weltweit.','home.explore'=>'Dienste entdecken','home.view_products'=>'Produkte ansehen','home.trusted'=>'Von Profis geschätzt','home.instant'=>'Sofortige Lieferung','home.secure'=>'Sicher & zuverlässig','home.active_services'=>'Aktive Dienste','home.countries'=>'Unterstützte Länder','home.support'=>'Experten-Support','home.delivery'=>'Digitale Lieferung','home.what_we_do'=>'Was wir tun','home.our_services'=>'Unsere Dienste','home.view_all_services'=>'Alle Dienste ansehen','home.latest_products'=>'Neueste Produkte','home.view_all_products'=>'Alle Produkte ansehen',
                'dashboard.welcome'=>'Willkommen zurück, :name','dashboard.summary'=>'Das ist heute in deinem Konto passiert.','dashboard.available_balance'=>'Verfügbares Guthaben','dashboard.locked'=>'Gesperrter Betrag','dashboard.receipts'=>'Erhaltenes Guthaben','dashboard.quick_order'=>'Schnellbestellung','dashboard.choose_service'=>'Wähle einen Diensttyp aus.','dashboard.recent_orders'=>'Letzte Bestellungen','dashboard.view_all_orders'=>'Alle Bestellungen ansehen','common.language'=>'Sprache','common.rights'=>'Alle Rechte vorbehalten.',
            ],
            'tr' => [
                'nav.home'=>'Ana sayfa','nav.services'=>'Hizmetler','nav.products'=>'Ürünler','nav.downloads'=>'İndirmeler','nav.resellers'=>'Bayiler','nav.dashboard'=>'Kontrol paneli','nav.login'=>'Giriş yap','nav.register'=>'Hesap oluştur','nav.logout'=>'Çıkış yap',
                'account.workspace'=>'Çalışma alanı','account.overview'=>'Genel bakış','account.place_order'=>'Yeni sipariş','account.all_orders'=>'Tüm siparişler','account.finance'=>'Finans ve hesap','account.add_funds'=>'Bakiye ekle','account.transactions'=>'İşlemler','account.settings'=>'Ayarlar','account.balance'=>'Bakiye','account.customer'=>'Müşteri',
                'home.kicker'=>'Küresel mobil çözümler','home.title'=>'Mobil profesyonellere güç katıyoruz','home.subtitle'=>'Mobil sektör için profesyonel araçlar, hizmetler ve destek — daha hızlı, akıllı ve dünya çapında.','home.explore'=>'Hizmetleri keşfet','home.view_products'=>'Ürünleri görüntüle','home.trusted'=>'Profesyonellerin tercihi','home.instant'=>'Anında teslimat','home.secure'=>'Güvenli ve güvenilir','home.active_services'=>'Aktif hizmetler','home.countries'=>'Desteklenen ülkeler','home.support'=>'Uzman destek','home.delivery'=>'Dijital teslimat','home.what_we_do'=>'Ne yapıyoruz','home.our_services'=>'Hizmetlerimiz','home.view_all_services'=>'Tüm hizmetleri gör','home.latest_products'=>'Yeni ürünler','home.view_all_products'=>'Tüm ürünleri gör',
                'dashboard.welcome'=>'Tekrar hoş geldiniz, :name','dashboard.summary'=>'Hesabınızda bugün olanlar.','dashboard.available_balance'=>'Kullanılabilir bakiye','dashboard.locked'=>'Kilitli tutar','dashboard.receipts'=>'Toplam kredi girişi','dashboard.quick_order'=>'Hızlı sipariş','dashboard.choose_service'=>'Başlamak için hizmet türünü seçin.','dashboard.recent_orders'=>'Son siparişler','dashboard.view_all_orders'=>'Tüm siparişleri gör','common.language'=>'Dil','common.rights'=>'Tüm hakları saklıdır.',
            ],
        ];

        $now = now();
        foreach ($languages as $code => [$name, $nativeName, $locale, $direction, $flag]) {
            DB::table('languages')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'native_name' => $nativeName,
                    'locale' => $locale,
                    'direction' => $direction,
                    'flag' => $flag,
                    'active' => true,
                    'ordering' => array_search($code, array_keys($languages), true),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
            $languageId = DB::table('languages')->where('code', $code)->value('id');
            foreach ($translations[$code] as $key => $value) {
                DB::table('language_translations')->updateOrInsert(
                    ['language_id' => $languageId, 'translation_key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        // Preserve languages and administrator-edited translations on rollback.
    }
};
