<?php

namespace App\Services\Auth;

use App\Models\Passkey;
use App\Models\User;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

final class PasskeyService
{
    private $serializer;
    private AuthenticatorAttestationResponseValidator $attestationValidator;
    private AuthenticatorAssertionResponseValidator $assertionValidator;

    public function __construct()
    {
        $manager = AttestationStatementSupportManager::create();
        $manager->add(NoneAttestationStatementSupport::create());
        $this->serializer = (new WebauthnSerializerFactory($manager))->create();
        $factory = new CeremonyStepManagerFactory();
        $this->attestationValidator = AuthenticatorAttestationResponseValidator::create($factory->creationCeremony());
        $this->assertionValidator = AuthenticatorAssertionResponseValidator::create($factory->requestCeremony());
    }

    public function registrationOptions(User $user, string $host): PublicKeyCredentialCreationOptions
    {
        return PublicKeyCredentialCreationOptions::create(
            PublicKeyCredentialRpEntity::create(config('app.name', 'GSM MIX'), $host),
            PublicKeyCredentialUserEntity::create($user->email, (string) $user->getKey(), $user->name),
            random_bytes(32),
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $user->passkeys->map(fn (Passkey $key) => $this->record($key)->getPublicKeyCredentialDescriptor())->all(),
            timeout: 300000,
        );
    }

    public function authenticationOptions(string $host): PublicKeyCredentialRequestOptions
    {
        return PublicKeyCredentialRequestOptions::create(
            random_bytes(32),
            rpId: $host,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 300000,
        );
    }

    public function register(User $user, string $name, string $payload, PublicKeyCredentialCreationOptions $options, string $host): Passkey
    {
        $credential = $this->credential($payload);
        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw new \InvalidArgumentException('Invalid passkey registration response.');
        }
        $record = $this->attestationValidator->check($credential->response, $options, $host);
        abort_unless(hash_equals((string) $user->getKey(), $record->userHandle), 403);

        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
            'credential' => $this->serialize($record),
        ]);
    }

    public function authenticate(string $payload, PublicKeyCredentialRequestOptions $options, string $host): Passkey
    {
        $credential = $this->credential($payload);
        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new \InvalidArgumentException('Invalid passkey authentication response.');
        }
        $passkey = Passkey::query()->with('user')->where('credential_id', Base64UrlSafe::encodeUnpadded($credential->rawId))->firstOrFail();
        abort_unless($passkey->user && $passkey->user->status === 'active', 403);
        $record = $this->assertionValidator->check($this->record($passkey), $credential->response, $options, $host, null);
        abort_unless(hash_equals((string) $passkey->user_id, $record->userHandle), 403);
        $passkey->forceFill(['credential' => $this->serialize($record), 'last_used_at' => now()])->save();

        return $passkey;
    }

    public function serialize(object $value): string
    {
        return $this->serializer->serialize($value, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
        ]);
    }

    public function creationOptions(string $json): PublicKeyCredentialCreationOptions
    {
        return $this->serializer->deserialize($json, PublicKeyCredentialCreationOptions::class, 'json');
    }

    public function requestOptions(string $json): PublicKeyCredentialRequestOptions
    {
        return $this->serializer->deserialize($json, PublicKeyCredentialRequestOptions::class, 'json');
    }

    private function credential(string $payload): PublicKeyCredential
    {
        return $this->serializer->deserialize($payload, PublicKeyCredential::class, 'json');
    }

    private function record(Passkey $passkey): CredentialRecord
    {
        return $this->serializer->deserialize($passkey->credential, CredentialRecord::class, 'json');
    }
}
