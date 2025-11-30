import { Component, inject, signal, computed, effect } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { DatePipe } from '@angular/common';
import { LearningService } from '../../services/learning.service';
import { TranslateModule } from '@ngx-translate/core';
import { RouterLink, Router } from '@angular/router';

@Component({
    selector: 'app-dashboard',
    imports: [DatePipe, TranslateModule, RouterLink],
    templateUrl: './dashboard.html'
})
export class DashboardComponent {
    private learningService = inject(LearningService);
    private router = inject(Router);

    activeTab = signal<'todo' | 'library'>('todo');

    // We'll use a signal for courses to make filtering easier and reactive
    courses = toSignal(this.learningService.getMyCourses(), { initialValue: [] });

    todoCourses = computed(() => this.courses().filter((c: any) => !c.is_completed));
    libraryCourses = computed(() => this.courses().filter((c: any) => c.is_completed));

    constructor() {
        // Auto-navigate to library if todo is empty but library has items
        effect(() => {
            const todo = this.todoCourses();
            const library = this.libraryCourses();
            const allCourses = this.courses();

            // Only run this check when we actually have loaded courses
            if (allCourses.length > 0 && todo.length === 0 && library.length > 0) {
                this.activeTab.set('library');
            }
        });
    }

    openCourse(courseId: number) {
        this.router.navigate(['/learn', courseId]);
    }
}
