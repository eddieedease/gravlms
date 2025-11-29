import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { AuthService } from './auth.service';

@Injectable({
    providedIn: 'root'
})
export class CourseService {
    private apiUrl = 'http://localhost:8080/api/pages';

    constructor(private http: HttpClient, private authService: AuthService) { }

    private getHeaders() {
        const token = this.authService.getToken();
        return new HttpHeaders({
            'Authorization': `Bearer ${token}`
        });
    }

    // Courses
    getCourses() {
        return this.http.get<any[]>('http://localhost:8080/api/courses', { headers: this.getHeaders() });
    }

    createCourse(course: any) {
        return this.http.post<any>('http://localhost:8080/api/courses', course, { headers: this.getHeaders() });
    }

    updateCourse(id: number, course: any) {
        return this.http.put<any>(`http://localhost:8080/api/courses/${id}`, course, { headers: this.getHeaders() });
    }

    deleteCourse(id: number) {
        return this.http.delete<any>(`http://localhost:8080/api/courses/${id}`, { headers: this.getHeaders() });
    }

    // Pages
    getPages() {
        return this.http.get<any[]>(this.apiUrl, { headers: this.getHeaders() });
    }

    createPage(page: any) {
        return this.http.post<any>(this.apiUrl, page, { headers: this.getHeaders() });
    }

    updatePage(id: number, page: any) {
        return this.http.put<any>(`${this.apiUrl}/${id}`, page, { headers: this.getHeaders() });
    }

    deletePage(id: number) {
        return this.http.delete<any>(`${this.apiUrl}/${id}`, { headers: this.getHeaders() });
    }

    // Tests
    getTests(courseId: number) {
        return this.http.get<any[]>(`http://localhost:8080/api/courses/${courseId}/tests`, { headers: this.getHeaders() });
    }

    getTest(id: number) {
        return this.http.get<any>(`http://localhost:8080/api/tests/${id}`, { headers: this.getHeaders() });
    }

    createTest(test: any) {
        return this.http.post<any>('http://localhost:8080/api/tests', test, { headers: this.getHeaders() });
    }

    updateTest(id: number, test: any) {
        return this.http.put<any>(`http://localhost:8080/api/tests/${id}`, test, { headers: this.getHeaders() });
    }

    deleteTest(id: number) {
        return this.http.delete<any>(`http://localhost:8080/api/tests/${id}`, { headers: this.getHeaders() });
    }
}
