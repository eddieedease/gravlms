import { Component, inject, signal, computed, effect } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { DatePipe } from '@angular/common';
import { LearningService } from '../../services/learning.service';
import { TranslateModule } from '@ngx-translate/core';
import { RouterLink, Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { ConfigService } from '../../services/config.service';

@Component({
    selector: 'app-dashboard',
    imports: [DatePipe, TranslateModule, RouterLink],
    templateUrl: './dashboard.html'
})
export class DashboardComponent {
    private learningService = inject(LearningService);
    private router = inject(Router);
    private authService = inject(AuthService);
    private apiService = inject(ApiService);
    private config = inject(ConfigService);

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
        // Check if this is an LTI course
        const course = [...this.todoCourses(), ...this.libraryCourses()].find((c: any) => c.id === courseId);

        if (course && course.is_lti && course.lti_tool_id) {
            // Launch LTI tool
            this.launchLtiCourse(course);
        } else {
            // Navigate to regular course viewer
            this.router.navigate(['/learn', courseId]);
        }
    }

    launchLtiCourse(course: any) {
        // For LTI 1.3, we need to initiate OIDC login
        // For LTI 1.1, we need to create a form with OAuth signature

        // Get the tool details
        this.apiService.getLtiTools().subscribe(tools => {
            const tool = tools.find((t: any) => t.id === course.lti_tool_id);

            if (!tool) {
                alert('LTI tool not found');
                return;
            }

            if (tool.lti_version === '1.3') {
                // LTI 1.3: Redirect to tool's initiate login URL
                const params = new URLSearchParams({
                    iss: window.location.origin,
                    login_hint: this.authService.currentUser()?.id.toString() || '',
                    target_link_uri: tool.tool_url,
                    lti_message_hint: course.id.toString()
                });

                window.location.href = `${tool.initiate_login_url}?${params.toString()}`;
            } else {
                // LTI 1.1: Create and submit a form
                this.launchLti11Tool(tool, course);
            }
        });
    }

    launchLti11Tool(tool: any, course: any) {
        // Use backend endpoint to generate properly signed OAuth parameters
        this.apiService.getLtiConsumerLaunchParams(tool.id, course.id).subscribe({
            next: (response) => {
                // Create and submit form with signed parameters
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
}
