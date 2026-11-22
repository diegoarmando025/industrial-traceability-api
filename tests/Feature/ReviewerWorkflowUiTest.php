<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReviewerWorkflowUiTest extends TestCase
{
    public function test_box_detail_explains_why_release_is_blocked(): void
    {
        $view = file_get_contents(resource_path('views/boxes/show.blade.php'));

        $this->assertStringContainsString('Faltan {{ count($pendingScans) }}', $view);
        $this->assertStringContainsString('Liberación pendiente de validaciones', $view);
        $this->assertStringContainsString('Selecciona la etiqueta a validar', $view);
        $this->assertStringContainsString('@disabled($pendingScans !== [])', $view);
    }
}
