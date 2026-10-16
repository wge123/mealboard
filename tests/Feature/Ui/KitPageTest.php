<?php

it('serves the kit page in the local environment', function () {
    $this->app['env'] = 'local';

    $this->get('/kit')->assertOk()->assertSee('Page header')->assertSee('Button');
});

it('answers 404 for the kit page outside the local environment', function (string $env) {
    $this->app['env'] = $env;

    $this->get('/kit')->assertNotFound();
})->with(['testing', 'production', 'staging']);
