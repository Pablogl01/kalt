<?php

it('blocks login after 10 failed attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/login', [
            'email'    => 'test@example.com',
            'password' => 'wrongpassword',
        ]);
    }

    $this->postJson('/api/login', [
        'email'    => 'test@example.com',
        'password' => 'wrongpassword',
    ])->assertStatus(429);
});
