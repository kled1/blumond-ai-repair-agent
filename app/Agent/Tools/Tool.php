<?php

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use Illuminate\Support\Facades\Validator;

/**
 * Base class for every tool the agent can call.
 *
 * - definition() is what Claude sees (name, description, JSON schema).
 * - rules() validates the input again on OUR side (never trust model output).
 * - handle() does the real work and returns an array (sent back as JSON).
 */
abstract class Tool
{
    abstract public function name(): string;

    abstract protected function description(): string;

    /** JSON schema "properties" for the tool input. */
    abstract protected function properties(): array;

    /** Laravel validation rules for the same input. */
    abstract protected function rules(): array;

    abstract protected function handle(array $input, AgentContext $ctx): array;

    public function definition(): array
    {
        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'strict' => true, // the API guarantees the input matches the schema
            'inputSchema' => [
                'type' => 'object',
                'properties' => $this->properties(),
                'required' => array_keys($this->properties()),
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * @throws ToolInputException when the input is invalid
     */
    public function execute(array $input, AgentContext $ctx): array
    {
        $validator = Validator::make($input, $this->rules());

        if ($validator->fails()) {
            throw new ToolInputException('Invalid input: '.implode(' ', $validator->errors()->all()));
        }

        return $this->handle($validator->validated(), $ctx);
    }
}
