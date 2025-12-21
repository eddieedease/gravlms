import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ConfigService } from './config.service';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl: string;

  constructor(private http: HttpClient, private config: ConfigService) {
    this.apiUrl = this.config.apiUrl;
  }

  uploadFile(file: File, type: string, contextId?: number): Observable<{ filename: string, url: string }> {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('type', type);
    if (contextId) {
      formData.append('course_id', contextId.toString());
    }
    return this.http.post<{ filename: string, url: string }>(`${this.apiUrl}/uploads`, formData);
  }

  // Groups
  getGroups(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups`);
  }

  createGroup(group: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups`, group);
  }

  updateGroup(id: number, group: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/groups/${id}`, group);
  }

  deleteGroup(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${id}`);
  }

  getGroupUsers(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/users`);
  }

  addUserToGroup(groupId: number, userId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/users`, { user_id: userId });
  }

  removeUserFromGroup(groupId: number, userId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/users/${userId}`);
  }

  getGroupCourses(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/courses`);
  }

  addCourseToGroup(groupId: number, courseId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/courses`, { course_id: courseId });
  }

  removeCourseFromGroup(groupId: number, courseId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/courses/${courseId}`);
  }

  // Group Monitors
  getGroupMonitors(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/monitors`);
  }

  addMonitorToGroup(groupId: number, userId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/monitors`, { user_id: userId });
  }

  removeMonitorFromGroup(groupId: number, userId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/monitors/${userId}`);
  }

  // Group Assessors
  getGroupAssessors(groupId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/groups/${groupId}/assessors`);
  }

  addAssessorToGroup(groupId: number, userId: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/groups/${groupId}/assessors`, { user_id: userId });
  }

  removeAssessorFromGroup(groupId: number, userId: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/groups/${groupId}/assessors/${userId}`);
  }

  // Assessments
  getAssessments(status: 'pending' | 'graded' = 'pending'): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/assessments/list`, { params: { status } });
  }

  getPendingAssessments(): Observable<any[]> {
    return this.getAssessments('pending');
  }

  getAssessmentForPage(pageId: number): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/assessments/page/${pageId}`);
  }

  submitAssessment(pageId: number, text: string, fileUrl: string | null): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/assessments/submit`, { page_id: pageId, text, file_url: fileUrl });
  }

  gradeAssessment(submissionId: number, status: 'passed' | 'failed', feedback: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/assessments/grade`, { submission_id: submissionId, status, feedback });
  }

  // Results
  getResults(params: any = {}): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/results`, { params });
  }

  exportResults(params: any = {}): Observable<any> {
    return this.http.get(`${this.apiUrl}/results/export`, { params, responseType: 'text' });
  }

  // Email
  sendTestEmail(email: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/admin/test-email`, { email });
  }

  getTestMessage(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/test`);
  }

  // LTI Management
  getLtiPlatforms(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/lti/platforms`);
  }

  createLtiPlatform(platform: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/admin/lti/platforms`, platform);
  }

  updateLtiPlatform(id: number, platform: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/admin/lti/platforms/${id}`, platform);
  }

  deleteLtiPlatform(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/admin/lti/platforms/${id}`);
  }

  getLtiTools(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/admin/lti/tools`);
  }

  createLtiTool(tool: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/admin/lti/tools`, tool);
  }

  updateLtiTool(id: number, tool: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/admin/lti/tools/${id}`, tool);
  }

  deleteLtiTool(id: number): Observable<any> {
    return this.http.delete<any>(`${this.apiUrl}/admin/lti/tools/${id}`);
  }

  getLtiConsumerLaunchParams(toolId: number, courseId?: number): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/lti/consumer/launch`, { tool_id: toolId, course_id: courseId });
  }
}
