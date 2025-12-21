import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from './auth.service';
import { ConfigService } from './config.service';
import { map } from 'rxjs/operators';

@Injectable({
    providedIn: 'root'
})
export class CourseService {
    private apiUrl: string;

    constructor(private http: HttpClient, private authService: AuthService, private config: ConfigService) {
        this.apiUrl = this.config.apiUrl;
    }

    // Courses
    getCourses() {
        return this.http.get<any[]>(`${this.apiUrl}/courses`);
    }

    getCourse(id: number) {
        return this.http.get<any>(`${this.apiUrl}/courses/${id}`);
    }

    createCourse(course: any) {
        return this.http.post<any>(`${this.apiUrl}/courses`, course);
    }

    updateCourse(id: number, course: any) {
        return this.http.put<any>(`${this.apiUrl}/courses/${id}`, course);
    }

    deleteCourse(id: number) {
        return this.http.delete<any>(`${this.apiUrl}/courses/${id}`);
    }

    // Pages (Course Items)
    getPages() {
        return this.http.get<any[]>(`${this.apiUrl}/pages`);
    }

    getCoursePages(courseId: number) {
        return this.getPages().pipe(
            map(pages => pages.filter(p => p.course_id == courseId)) // using == for loose comparison if string/num mismatch
        );
    }

    createPage(page: any) {
        return this.http.post<any>(`${this.apiUrl}/pages`, page);
    }

    updatePage(id: number, page: any) {
        return this.http.put<any>(`${this.apiUrl}/pages/${id}`, page);
    }

    deletePage(id: number) {
        return this.http.delete<any>(`${this.apiUrl}/pages/${id}`);
    }

    // Tests (Linked to Page ID)
    getTestByPageId(pageId: number) {
        return this.http.get<any>(`${this.apiUrl}/pages/${pageId}/test`);
    }

    // Create/Update test for a page
    saveTest(test: any) {
        return this.http.post<any>(`${this.apiUrl}/tests`, test);
    }

    submitTest(testId: number, answers: any) {
        return this.http.post<any>(`${this.apiUrl}/tests/${testId}/submit`, { answers });
    }

    uploadCourseImage(file: File, courseId?: number, type: 'thumbnail' | 'content' | 'misc' = 'thumbnail') {
        const formData = new FormData();
        formData.append('image', file);
        if (courseId) {
            formData.append('course_id', courseId.toString());
        }
        formData.append('type', type);
        return this.http.post<any>(`${this.apiUrl}/uploads`, formData);
    }
}
