<?php

declare(strict_types=1);

namespace App\Http\Requests\Categories;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $category = $this->route('category');

        return $user !== null
            && $category instanceof Category
            && $category->user_id === $user->id;
    }

    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');
        $categoryId = $category->id;
        $userId = $this->user()->id;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories')
                    ->ignore($categoryId)
                    ->where(static function ($query) use ($userId): void {
                        $query
                            ->where('user_id', $userId);
                    }),
            ],
        ];
    }
}
