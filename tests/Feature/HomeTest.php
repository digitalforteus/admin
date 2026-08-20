<?php

use App\Models\User;
use App\Routes\Web;
use Illuminate\Support\Facades\Config;

test('home ok', function (): void {
    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('href="'.Web::login->value.'"', false)
        ->assertSee('href="'.Web::contact->value.'"', false)
        ->assertSee('>Login</span>', false)
        ->assertSee('>Contact</span>', false)
        ->assertSee(Config::string('app.name'))
        ->assertSee(Config::string('brand.logo_title'));
});

test('the attribution lockup renders while attribution is enabled', function (): void {
    config()->set('brand.attribution', true);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('data-digitalforte-link="header_lockup"', false)
        ->assertSee('data-digitalforte-link="footer_attribution"', false)
        ->assertSee('text-digitalforte-primary', false)
        ->assertSee('text-digitalforte-secondary', false)
        ->assertSee('digitalforte_referral_click');
});

test('the attribution lockup is omitted while attribution is disabled', function (): void {
    config()->set('brand.attribution', false);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-digitalforte-link', false)
        ->assertDontSee('digitalforte_referral_click')
        ->assertSee(Config::string('app.name'));
});

test('the home login section is hidden from authenticated users', function (): void {
    $this->actingAs(User::factory()->createOne())
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-home-login', false)
        ->assertSee('href="'.Web::contact->value.'"', false);
});
