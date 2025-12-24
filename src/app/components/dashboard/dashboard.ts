import { Component, inject, signal, computed, effect, OnInit, HostListener } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { LearningService } from '../../services/learning.service';
import { TranslateModule } from '@ngx-translate/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { ConfigService } from '../../services/config.service';
import { OrganisationService } from '../../services/organisation.service';

@Component({
    selector: 'app-dashboard',
    imports: [TranslateModule, FormsModule],
    templateUrl: './dashboard.html'
})
export class DashboardComponent implements OnInit {
    private learningService = inject(LearningService);
    private router = inject(Router);
    private authService = inject(AuthService);
    private apiService = inject(ApiService);
    private config = inject(ConfigService);
    public orgService = inject(OrganisationService);

    activeTab = signal<'todo' | 'library'>('todo');

    courses = signal<any[]>([]);

    // Search functionality
    allLessons = signal<any[]>([]);
    searchQuery = signal<string>('');
    searchDropdownOpen = signal<boolean>(false);
    selectedResultIndex = signal<number>(-1);

    filteredLessons = computed(() => {
        const query = this.searchQuery().toLowerCase().trim();
        if (!query) return [];

        return this.allLessons()
            .filter(lesson => lesson.title.toLowerCase().includes(query))
            .slice(0, 10); // Limit to 10 results
    });

    todoCourses = computed(() => this.courses().filter((c: any) => c.status === 'todo' || c.status === 'expired'));
    libraryCourses = computed(() => this.courses().filter((c: any) => c.status === 'completed'));

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

    ngOnInit() {
        // If user is in LTI mode, redirect them to their course
        if (this.authService.isLtiMode() && this.authService.ltiCourseId()) {
            this.router.navigate(['/learn', this.authService.ltiCourseId()]);
            return;
        }
        this.loadCourses();
        this.loadLessons();
    }

    loadCourses() {
        this.learningService.getMyCourses().subscribe(courses => {
            this.courses.set(courses);
        });
    }

    loadLessons() {
        this.learningService.getMyLessons().subscribe(lessons => {
            this.allLessons.set(lessons);
        });
    }

    openCourse(courseId: number) {
        // Check if this is an LTI course
        const course = [...this.todoCourses(), ...this.libraryCourses()].find((c: any) => c.id === courseId);

        if (!course) return;

        if (course.status === 'expired') {
            if (confirm('This course has expired. Do you want to retake it? (Previous progress will be reset, but history is saved)')) {
                this.resetAndLaunch(course);
            }
            return;
        }

        // Navigate to course viewer for both regular and LTI courses
        // The CourseViewerComponent will handle the LTI launch within an iframe
        this.router.navigate(['/learn', courseId]);
    }

    resetCourse(course: any) {
        if (confirm('Do you want to retake this course? Your current progress will be reset, but your completion history is saved.')) {
            this.resetAndLaunch(course);
        }
    }

    resetAndLaunch(course: any) {
        this.learningService.resetCourse(course.id).subscribe(() => {
            // Reload courses to update status
            this.loadCourses();
            // Then launch
            // Then launch
            this.router.navigate(['/learn', course.id]);
        });
    }

    launchLtiCourse(course: any) {
        if (!course.lti_tool_id) return;

        // Use backend endpoint to generate launch parameters or OIDC URL
        this.apiService.getLtiConsumerLaunchParams(course.lti_tool_id, course.id).subscribe({
            next: (response) => {
                if (response.type === 'LTI-1p3') {
                    // LTI 1.3: Redirect to OIDC Login URL
                    window.location.href = response.url;
                } else if (response.type === 'LTI-1p0') {
                    // LTI 1.1: Create and submit a form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = response.url;
                    form.style.display = 'none';

                    for (const [key, value] of Object.entries(response.params)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value as string;
                        form.appendChild(input);
                    }

                    document.body.appendChild(form);
                    form.submit();
                }
            },
            error: (err) => {
                console.error('LTI launch error:', err);
                alert('Failed to launch LTI tool: ' + (err.error?.error || 'Unknown error'));
            }
        });
    }

    getImageUrl(imageUrl: string): string {
        return `${this.config.apiUrl}/uploads/${imageUrl}`;
    }

    // Search methods
    onSearchInput(value: string) {
        this.searchQuery.set(value);
        this.selectedResultIndex.set(-1);
        this.searchDropdownOpen.set(value.trim().length > 0);
    }

    navigateToLesson(lesson: any) {
        this.searchQuery.set('');
        this.searchDropdownOpen.set(false);
        this.router.navigate(['/learn', lesson.course_id], {
            queryParams: { pageId: lesson.id }
        });
    }

    onSearchKeydown(event: KeyboardEvent) {
        const results = this.filteredLessons();
        if (!results.length) return;

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.selectedResultIndex.update(i =>
                    i < results.length - 1 ? i + 1 : i
                );
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.selectedResultIndex.update(i => i > 0 ? i - 1 : -1);
                break;
            case 'Enter':
                event.preventDefault();
                const index = this.selectedResultIndex();
                if (index >= 0 && index < results.length) {
                    this.navigateToLesson(results[index]);
                }
                break;
            case 'Escape':
                this.searchQuery.set('');
                this.searchDropdownOpen.set(false);
                break;
        }
    }

    @HostListener('document:click', ['$event'])
    onDocumentClick(event: MouseEvent) {
        const target = event.target as HTMLElement;
        const searchContainer = target.closest('.lesson-search-container');

        if (!searchContainer && this.searchDropdownOpen()) {
            this.searchDropdownOpen.set(false);
        }
    }
}
