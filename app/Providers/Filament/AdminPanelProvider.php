<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use App\Filament\Widgets\TodayOverview;
use Filament\Panel;
use Filament\PanelProvider;
use App\Support\BrandPalette;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->brandName('Srisawan Hybrid Workout')
            ->favicon(asset('favicon.png'))
            ->brandLogo(asset('img/logo@180.png'))
            ->brandLogoHeight('2rem')
            // ฟอนต์และสีชุดเดียวกับหน้าบ้านและ hybridssw.srisawan.com
            ->font('IBM Plex Sans Thai', provider: GoogleFontProvider::class)
            ->colors([
                'primary' => BrandPalette::Lagoon,
                'gray' => BrandPalette::Stone,
                'success' => BrandPalette::Leaf,
                'info' => BrandPalette::Lagoon,
                'warning' => BrandPalette::Gold,
                'danger' => BrandPalette::Clay,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                TodayOverview::class,
                \App\Filament\Widgets\PendingTrainers::class,
                \App\Filament\Widgets\TodaySessions::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::SevenExtraLarge)
            // สไตล์ของแบรนด์แทรกทีหลัง CSS ของ Filament จึงทับค่าเดิมได้
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/css/filament-admin.css')"),
            );
    }
}
