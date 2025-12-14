import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from './auth.service';
import { ConfigService } from './config.service';

@Injectable({
    providedIn: 'root'
})
export class LearningService {
    private apiUrl: string;
    private http = inject(HttpClient);
    private authService = inject(AuthService);
    private config = inject(ConfigService);

    constructor() {
        this.apiUrl = `${this.config.apiUrl}/learning`;
    }

    private getHeaders() {
        const token = this.authService.getToken();
        return new HttpHeaders({
            'Authorization': `Bearer ${token}`
        });
    }

    assignCourse(userId: number, courseId: number) {
        return this.http.post<any>(`${this.apiUrl}/assign`, { user_id: userId, course_id: courseId }, { headers: this.getHeaders() });
    }

    detachCourse(userId: number, courseId: number) {
        return this.http.delete<any>(`${this.apiUrl}/assign/${userId}/${courseId}`, { headers: this.getHeaders() });
    }

    getMyCourses() {
        return this.http.get<any[]>(`${this.apiUrl}/my-courses`, { headers: this.getHeaders() });
    }

    getUserCourses(userId: number) {
        return this.http.get<any[]>(`${this.apiUrl}/user-courses/${userId}`, { headers: this.getHeaders() });
    }

    getCourseProgress(courseId: number) {
        return this.http.get<any>(`${this.apiUrl}/progress/${courseId}`, { headers: this.getHeaders() });
    }

    completeLesson(courseId: number, pageId: number) {
        return this.http.post<any>(`${this.apiUrl}/complete-lesson`, { course_id: courseId, page_id: pageId }, { headers: this.getHeaders() });
    }

    resetCourse(courseId: number) {
        return this.http.post<any>(`${this.apiUrl}/reset/${courseId}`, {}, { headers: this.getHeaders() });
    }
}
