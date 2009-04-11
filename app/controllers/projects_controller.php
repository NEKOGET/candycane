<?php
## redMine - project management software
## Copyright (C) 2006-2007  Jean-Philippe Lang
##
## This program is free software; you can redistribute it and/or
## modify it under the terms of the GNU General Public License
## as published by the Free Software Foundation; either version 2
## of the License, or (at your option) any later version.
## 
## This program is distributed in the hope that it will be useful,
## but WITHOUT ANY WARRANTY; without even the implied warranty of
## MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
## GNU General Public License for more details.
## 
## You should have received a copy of the GNU General Public License
## along with this program; if not, write to the Free Software
## Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
class ProjectsController extends AppController
{
  var $name = 'Projects';
  var $uses = array('Project', 'User', 'Tracker', 'IssueCustomField', 'Permission', 'CustomFieldsProject', 'EnabledModule');
  var $helpers = array('Time', 'Project');
  var $components = array('RequestHandler');

#  menu_item :overview
#  menu_item :activity, :only => :activity
#  menu_item :roadmap, :only => :roadmap
#  menu_item :files, :only => [:list_files, :add_file]
#  menu_item :settings, :only => :settings
#  menu_item :issues, :only => [:changelog]
  
  /**
   * beforeFilter
   *
   * before_filter :find_project, :except => [ :index, :list, :add, :activity ]
   * before_filter :find_optional_project, :only => :activity
   * before_filter :authorize, :except => [ :index, :list, :add, :archive, :unarchive, :destroy, :activity ]
   * before_filter :require_admin, :only => [ :add, :archive, :unarchive, :destroy ]
   *
   */
  function beforeFilter()
  {
    parent::beforeFilter();

    $except = array('index', 'list', 'add', 'activity');
    if (!in_array($this->action, $except)) {
      $this->find_project();
    }
    /*

    if ($this->action == 'activity') {
      $this->find_optional_project();
    }

    $except = array('index', 'list', 'add', 'archive', 'unarchive', 'destroy', 'activity');
    if (!in_array($this->action, $except)) {
      $this->authorize();
    }
     */

    $only = array('add', 'archive', 'unarchive', 'destroy');
    if (in_array($this->action, $only)) {
      $this->require_admin();
    }
  }
#  accept_key_auth :activity
#  
#  helper :sort
#  include SortHelper
#  helper :custom_fields
#  include CustomFieldsHelper   
#  helper :issues
#  helper IssuesHelper
#  helper :queries
#  include QueriesHelper
#  helper :repositories
#  include RepositoriesHelper
#  include ProjectsHelper
#  
#  # Lists visible projects
#  def index
#    projects = Project.find :all,
#                            :conditions => Project.visible_by(User.current),
#                            :include => :parent
#    respond_to do |format|
#      format.html { 
#        @project_tree = projects.group_by {|p| p.parent || p}
#        @project_tree.keys.each {|p| @project_tree[p] -= [p]} 
#      }
#      format.atom {
#        render_feed(projects.sort_by(&:created_on).reverse.slice(0, Setting.feeds_limit.to_i), 
#                                  :title => "#{Setting.app_title}: #{l(:label_project_latest)}")
#      }
#    end
#  end
  function index()
  {
    $projects = $this->Project->find('all'); // *not implement* => User.current
    foreach ($projects as $key => $val) {
      foreach ($val as $key2 => $val2) {
        if (empty($val2['parent_id'])) {
          $project_tree[] = $val2;
        } else {
          $sub_project_tree[ $val2['parent_id'] ][] = $val2;
        }
      }
    }
    $this->set('project_tree', $project_tree);
    $this->set('sub_project_tree', $sub_project_tree);
  }

  function add()
  {
    $trackers = $this->Tracker->find('all');
    $this->set('trackers', $trackers);

#    @issue_custom_fields = IssueCustomField.find(:all, :order => "#{CustomField.table_name}.position")
    $issue_custom_fields = $this->IssueCustomField->find('all', array('order'=>$this->IssueCustomField->name.".position"));
    $this->set('issue_custom_fields', $issue_custom_fields);

    $root_project_inputs = $this->Project->find('all', array('conditions'=>array($this->Project->name.'.parent_id'=>NULL, $this->Project->name.'.status'=>PROJECT_STATUS_ACTIVE), 'order'=>$this->Project->name.'.name'));
    $root_projects = array(null=>'');
    foreach($root_project_inputs as $project) {
      $root_projects[$project['Project']['id']] = $project['Project']['name'];
    }
    $this->set('root_projects', $root_projects);

#      @project.enabled_module_names = Redmine::AccessControl.available_project_modules
    $enabled_module_names = $this->Permission->available_project_modules();
    $this->set('enabled_module_names', $enabled_module_names);

    if(!empty($this->data)) {
      if($this->Project->save($this->data, true, array('name', 'description', 'parent_id', 'identifier', 'homepage', 'is_public'))) {
        foreach($this->data['Project']['tracker_ids'] as $tracker_id) {
        }
        foreach($this->data['Project']['issue_custom_field_ids'] as $custom_field_id) {
          $this->CustomFieldsProject->save(array('custom_field_id'=>$custom_field_id, 'project_id'=>$this->data->id));
        }
        foreach($this->data['Project']['enabledModules'] as $enabledModule) {
          $this->EnabledModule->save(array('name'=>$enabledModule, 'project_id'=>$this->data->id));
        }
        $this->Session->setFlash(__('Successful create.'));
        $this->redirect('/admin/projects');
      }
    }
  }
#  
#  # Add a new project
#  def add
#    @issue_custom_fields = IssueCustomField.find(:all, :order => "#{CustomField.table_name}.position")
#    @trackers = Tracker.all
#    @root_projects = Project.find(:all,
#                                  :conditions => "parent_id IS NULL AND status = #{Project::STATUS_ACTIVE}",
#                                  :order => 'name')
#    @project = Project.new(params[:project])
#    if request.get?
#      @project.identifier = Project.next_identifier if Setting.sequential_project_identifiers?
#      @project.trackers = Tracker.all
#      @project.is_public = Setting.default_projects_public?
#      @project.enabled_module_names = Redmine::AccessControl.available_project_modules
#    else
#      @project.enabled_module_names = params[:enabled_modules]
#      if @project.save
#        flash[:notice] = l(:notice_successful_create)
#        redirect_to :controller => 'admin', :action => 'projects'
#	  end		
#    end	
#  end
#	
#  # Show @project
#  def show
#    if params[:jump]
#      # try to redirect to the requested menu item
#      redirect_to_project_menu_item(@project, params[:jump]) && return
#    end
#    
#    @members_by_role = @project.members.find(:all, :include => [:user, :role], :order => 'position').group_by {|m| m.role}
#    @subprojects = @project.children.find(:all, :conditions => Project.visible_by(User.current))
#    @news = @project.news.find(:all, :limit => 5, :include => [ :author, :project ], :order => "#{News.table_name}.created_on DESC")
#    @trackers = @project.rolled_up_trackers
#    
#    cond = @project.project_condition(Setting.display_subprojects_issues?)
#    Issue.visible_by(User.current) do
#      @open_issues_by_tracker = Issue.count(:group => :tracker,
#                                            :include => [:project, :status, :tracker],
#                                            :conditions => ["(#{cond}) AND #{IssueStatus.table_name}.is_closed=?", false])
#      @total_issues_by_tracker = Issue.count(:group => :tracker,
#                                            :include => [:project, :status, :tracker],
#                                            :conditions => cond)
#    end
#    TimeEntry.visible_by(User.current) do
#      @total_hours = TimeEntry.sum(:hours, 
#                                   :include => :project,
#                                   :conditions => cond).to_f
#    end
#    @key = User.current.rss_key
#  end
  function show()
  {
  }
#
#  def settings
#    @root_projects = Project.find(:all,
#                                  :conditions => ["parent_id IS NULL AND status = #{Project::STATUS_ACTIVE} AND id <> ?", @project.id],
#                                  :order => 'name')
#    @issue_custom_fields = IssueCustomField.find(:all, :order => "#{CustomField.table_name}.position")
#    @issue_category ||= IssueCategory.new
#    @member ||= @project.members.new
#    @trackers = Tracker.all
#    @repository ||= @project.repository
#    @wiki ||= @project.wiki
#  end
#  
#  # Edit @project
#  def edit
#    if request.post?
#      @project.attributes = params[:project]
#      if @project.save
#        flash[:notice] = l(:notice_successful_update)
#        redirect_to :action => 'settings', :id => @project
#      else
#        settings
#        render :action => 'settings'
#      end
#    end
#  end
#  
#  def modules
#    @project.enabled_module_names = params[:enabled_modules]
#    redirect_to :action => 'settings', :id => @project, :tab => 'modules'
#  end
#
#  def archive
#    @project.archive if request.post? && @project.active?
#    redirect_to :controller => 'admin', :action => 'projects'
#  end
#  
#  def unarchive
#    @project.unarchive if request.post? && !@project.active?
#    redirect_to :controller => 'admin', :action => 'projects'
#  end
#  
  function destroy()
  {
    if($this->RequestHandler->isPost()) {
      if ($this->data['Project']['confirm'] == 1) {
        $this->Project->del($this->data['Project']['id']);
        $this->redirect('/admin/projects');
      } else {
      }
    }
  }
#  # Delete @project
#  def destroy
#    @project_to_destroy = @project
#    if request.post? and params[:confirm]
#      @project_to_destroy.destroy
#      redirect_to :controller => 'admin', :action => 'projects'
#    end
#    # hide project in layout
#    @project = nil
#  end
#	
#  # Add a new issue category to @project
#  def add_issue_category
#    @category = @project.issue_categories.build(params[:category])
#    if request.post? and @category.save
#  	  respond_to do |format|
#        format.html do
#          flash[:notice] = l(:notice_successful_create)
#          redirect_to :action => 'settings', :tab => 'categories', :id => @project
#        end
#        format.js do
#          # IE doesn't support the replace_html rjs method for select box options
#          render(:update) {|page| page.replace "issue_category_id",
#            content_tag('select', '<option></option>' + options_from_collection_for_select(@project.issue_categories, 'id', 'name', @category.id), :id => 'issue_category_id', :name => 'issue[category_id]')
#          }
#        end
#      end
#    end
#  end
#	
#  # Add a new version to @project
#  def add_version
#  	@version = @project.versions.build(params[:version])
#  	if request.post? and @version.save
#  	  flash[:notice] = l(:notice_successful_create)
#      redirect_to :action => 'settings', :tab => 'versions', :id => @project
#  	end
#  end
  function add_version()
  {
    if(!empty($this->data)) {
      if($this->Version->save($this->data, true, array('name', 'description', 'wiki_page_title', 'effective_date'))) {
        $this->Session->setFlash(__('Successful create.'));
        $this->redirect('/settings/versions/'.$this->data['Project']['id']);
      }
    }
  }
#
#  def add_file
#    if request.post?
#      container = (params[:version_id].blank? ? @project : @project.versions.find_by_id(params[:version_id]))
#      attachments = attach_files(container, params[:attachments])
#      if !attachments.empty? && Setting.notified_events.include?('file_added')
#        Mailer.deliver_attachments_added(attachments)
#      end
#      redirect_to :controller => 'projects', :action => 'list_files', :id => @project
#      return
#    end
#    @versions = @project.versions.sort
#  end
  function add_file()
  {

  }
#  
#  def list_files
#    sort_init 'filename', 'asc'
#    sort_update 'filename' => "#{Attachment.table_name}.filename",
#                'created_on' => "#{Attachment.table_name}.created_on",
#                'size' => "#{Attachment.table_name}.filesize",
#                'downloads' => "#{Attachment.table_name}.downloads"
#                
#    @containers = [ Project.find(@project.id, :include => :attachments, :order => sort_clause)]
#    @containers += @project.versions.find(:all, :include => :attachments, :order => sort_clause).sort.reverse
#    render :layout => !request.xhr?
#  end
  function list_files()
  {
    $containers = array();
    $this->set('containers', $containers);
  }
#  
#  # Show changelog for @project
#  def changelog
#    @trackers = @project.trackers.find(:all, :conditions => ["is_in_chlog=?", true], :order => 'position')
#    retrieve_selected_tracker_ids(@trackers)    
#    @versions = @project.versions.sort
#  end
  function changelog()
  {

  }
#
#  def roadmap
#    @trackers = @project.trackers.find(:all, :conditions => ["is_in_roadmap=?", true])
#    retrieve_selected_tracker_ids(@trackers)
#    @versions = @project.versions.sort
#    @versions = @versions.select {|v| !v.completed? } unless params[:completed]
#  end
  function roadmap()
  {
    // $issues = $this->Version->FixedIssue->find('all', 
    $this->set('issues', array());

    /*
    <% issues = version.fixed_issues.find(:all,
                                          :include => [:status, :tracker],
                                          :conditions => ["tracker_id in (#{@selected_tracker_ids.join(',')})"],
                                          :order => "#{Tracker.table_name}.position, #{Issue.table_name}.id") unless @selected_tracker_ids.empty?
       issues ||= []
    %>
     */

  }
#  
#  def activity
#    @days = Setting.activity_days_default.to_i
#    
#    if params[:from]
#      begin; @date_to = params[:from].to_date + 1; rescue; end
#    end
#
#    @date_to ||= Date.today + 1
#    @date_from = @date_to - @days
#    @with_subprojects = params[:with_subprojects].nil? ? Setting.display_subprojects_issues? : (params[:with_subprojects] == '1')
#    @author = (params[:user_id].blank? ? nil : User.active.find(params[:user_id]))
#    
#    @activity = Redmine::Activity::Fetcher.new(User.current, :project => @project, 
#                                                             :with_subprojects => @with_subprojects,
#                                                             :author => @author)
#    @activity.scope_select {|t| !params["show_#{t}"].nil?}
#    @activity.scope = (@author.nil? ? :default : :all) if @activity.scope.empty?
#
#    events = @activity.events(@date_from, @date_to)
#    
#    respond_to do |format|
#      format.html { 
#        @events_by_day = events.group_by(&:event_date)
#        render :layout => false if request.xhr?
#      }
#      format.atom {
#        title = l(:label_activity)
#        if @author
#          title = @author.name
#        elsif @activity.scope.size == 1
#          title = l("label_#{@activity.scope.first.singularize}_plural")
#        end
#        render_feed(events, :title => "#{@project || Setting.app_title}: #{title}")
#      }
#    end
#    
#  rescue ActiveRecord::RecordNotFound
#    render_404
#  end
  function activity()
  {

  }
#  
#private
#  # Find project of id params[:id]
#  # if not found, redirect to project list
#  # Used as a before_filter
#  def find_project
#    @project = Project.find(params[:id])
#  rescue ActiveRecord::RecordNotFound
#    render_404
#  end
  function find_project()
  {
    $data = null;
    if (!empty($this->data)) {
      $data = $this->data;
    }
    if (!empty($this->params['project_id'])) {
      $this->data = $this->Project->findByIdentifier($this->params['project_id']);
      if (!$this->data) {
        $this->data = $this->Project->findById($this->params['project_id']);
      }
      $this->id = $this->data['Project']['id'];
    } else if (!empty($this->params['id'])) {
      $this->id = $this->params['id'];
      $this->data = $this->Project->read();
    } else if (isset($this->data['Project'])) {
      if (isset($this->data['Project']['id'])) {
        $this->id = $this->data['Project']['id'];
        $this->data = $this->Project->read();
      }
    }

    if (!empty($data)) {
      foreach($data as $key=>$value) {
        if (is_array($value)) {
          foreach($value as $key2=>$value2) {
            $this->data[$key][$key2] = $value2;
          }
        } else {
          $this->data[$key] = $value;
        }
      }
    }


  }
#  
#  def find_optional_project
#    return true unless params[:id]
#    @project = Project.find(params[:id])
#    authorize
#  rescue ActiveRecord::RecordNotFound
#    render_404
#  end
#
#  def retrieve_selected_tracker_ids(selectable_trackers)
#    if ids = params[:tracker_ids]
#      @selected_tracker_ids = (ids.is_a? Array) ? ids.collect { |id| id.to_i.to_s } : ids.split('/').collect { |id| id.to_i.to_s }
#    else
#      @selected_tracker_ids = selectable_trackers.collect {|t| t.id.to_s }
#    end
#  end
#end
	
	function settings($project_name)
	{

	}

}
?>
