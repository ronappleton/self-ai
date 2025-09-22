<?php

return [
    'immutable_path' => env('IMMUTABLE_POLICY_PATH', 'policy/immutable-policy.yaml'),
    'signature_path' => env('IMMUTABLE_POLICY_SIGNATURE_PATH', 'policy/immutable-policy.sig'),
    'public_key_path' => env('IMMUTABLE_POLICY_PUBLIC_KEY_PATH', 'policy/owner-public.pem'),
];
