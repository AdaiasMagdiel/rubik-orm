<?php

namespace AdaiasMagdiel\Rubik\Traits;

trait SerializationTrait
{
    /**
     * Defines how the model will be serialized into JSON.
     *
     * @return array The data to be serialized into JSON.
     */
    public function toArray(): array
    {
        $result = $this->_data; // Includes all model data

        // Optionally, include loaded relationships
        foreach ($this->relationships as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Defines how the model will be serialized into JSON.
     *
     * @return array The data to be serialized into JSON.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
