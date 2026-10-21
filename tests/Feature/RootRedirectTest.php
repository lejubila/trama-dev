<?php

declare(strict_types=1);

it('redirects the site root to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});
