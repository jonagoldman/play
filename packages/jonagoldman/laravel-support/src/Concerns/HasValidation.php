<?php

declare(strict_types=1);

namespace JonaGoldman\Support\Concerns;

use Illuminate\Contracts\Validation\Factory as ValidationFactoryContract;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\Validator;

trait HasValidation
{
    protected ?Validator $validator;

    /**
     * Run the validator's rules against its data.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validate(array $data): array
    {
        $this->validator = $this->makeValidator($data);

        return $this->validator->validate();
    }

    /**
     * Get validation rules to validate against.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [];
    }

    // public function validate(array $data, bool $throw = true): array
    // {
    //     $this->validator = $validator = $this->makeValidator($data);

    //     if ($validator->fails()) {
    //         throw_if($throw, $validator->getException(), $validator);

    //         return $validator->errors()->toArray();
    //     }

    //     return $validator->validated();
    // }

    protected function makeValidator(array $data): Validator
    {
        return $this->getValidationFactory()->make($data, $this->rules(), $this->messages(), $this->attributes());
    }

    protected function getValidationFactory(): ValidationFactory
    {
        return app(ValidationFactoryContract::class);
    }
}
