<?php

use App\Models\User;
use App\Routes\Web;
use Illuminate\Support\Facades\Config;

test('the home page links to login and contact, and hides the login section from a signed in user', function (): void {
    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('href="'.Web::login->value.'"', false)
        ->assertSee('href="'.Web::contact->value.'"', false)
        ->assertSee('>Login</span>', false)
        ->assertSee('>Contact</span>', false)
        ->assertSee(Config::string('app.name'))
        ->assertSee(Config::string('brand.logo_title'));

    $this->actingAs(User::factory()->createOne())
        ->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-home-login', false)
        ->assertSee('href="'.Web::contact->value.'"', false);
});

test('the attribution lockup renders only while attribution is enabled', function (): void {
    config()->set('brand.attribution', true);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertSee('data-digitalforte-link="header_lockup"', false)
        ->assertSee('data-digitalforte-link="footer_attribution"', false)
        ->assertSee('text-digitalforte-primary', false)
        ->assertSee('text-digitalforte-secondary', false)
        ->assertSee('digitalforte_referral_click');

    config()->set('brand.attribution', false);

    $this->get(Web::home->value)
        ->assertOk()
        ->assertDontSee('data-digitalforte-link', false)
        ->assertDontSee('digitalforte_referral_click')
        ->assertSee(Config::string('app.name'));
});
