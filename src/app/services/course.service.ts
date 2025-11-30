import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from './auth.service';

@Injectable({
    providedIn: 'root'
})
export class CourseService {
    private apiUrl = 'http://localhost:8080/api/pages';

    constructor(private http: HttpClient, private authService: AuthService) { }

    // Courses
    getCourses() {
        return this.http.get<any[]>('http://localhost:8080/api/courses');
    }

    createCourse(course: any) {
        return this.http.post<any>('http://localhost:8080/api/courses', course);
    }

    updateCourse(id: number, course: any) {
        return this.http.put<any>(`http://localhost:8080/api/courses/${id}`, course);
    }

    deleteCourse(id: number) {
        return this.http.delete<any>(`http://localhost:8080/api/courses/${id}`);
    }

    // Pages (Course Items)
    getPages() {
        return this.http.get<any[]>(this.apiUrl);
    }

    createPage(page: any) {
        return this.http.post<any>(this.apiUrl, page);
    }

    updatePage(id: number, page: any) {
        return this.http.put<any>(`${this.apiUrl}/${id}`, page);
    }

    deletePage(id: number) {
        return this.http.delete<any>(`${this.apiUrl}/${id}`);
    }

    // Tests (Linked to Page ID)
    getTestByPageId(pageId: number) {
        return this.http.get<any>(`http://localhost:8080/api/pages/${pageId}/test`);
    }

    // Create/Update test for a page
    saveTest(test: any) {
        return this.http.post<any>('http://localhost:8080/api/tests', test);
    }

    submitTest(testId: number, answers: any) {
        return this.http.post<any>(`http://localhost:8080/api/tests/${testId}/submit`, { answers });
    }
}
